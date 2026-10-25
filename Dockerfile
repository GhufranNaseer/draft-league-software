FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources resources
COPY public public
COPY vite.config.js ./

ARG VITE_APP_NAME="Participant Management System"
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_APP_NAME="${VITE_APP_NAME}" \
    VITE_REVERB_APP_KEY="${VITE_REVERB_APP_KEY}" \
    VITE_REVERB_HOST="${VITE_REVERB_HOST}" \
    VITE_REVERB_PORT="${VITE_REVERB_PORT}" \
    VITE_REVERB_SCHEME="${VITE_REVERB_SCHEME}"

RUN npm run build

FROM php:8.2-fpm-alpine AS php-base

WORKDIR /var/www/html

RUN apk add --no-cache \
        bash \
        fcgi \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        mariadb-client \
        oniguruma-dev \
        sqlite-dev \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_sqlite \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY app app
COPY database database
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM php-base AS app

COPY docker/php/php.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php-fpm/zz-production.conf /usr/local/etc/php-fpm.d/zz-production.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor vendor
COPY --from=assets --chown=www-data:www-data /app/public/build public/build

RUN chmod +x /usr/local/bin/docker-entrypoint \
    && sed -i 's/\r$//' /usr/local/bin/docker-entrypoint \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && ln -s /var/www/html/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
