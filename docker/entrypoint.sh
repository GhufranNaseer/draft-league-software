#!/usr/bin/env sh
set -e

if [ "${DB_CONNECTION:-}" = "mysql" ] || [ "${DB_CONNECTION:-}" = "mariadb" ]; then
    until mysqladmin ping -h"${DB_HOST:-database}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-laravel}" -p"${DB_PASSWORD:-}" --silent; do
        echo "Waiting for database..."
        sleep 2
    done
fi

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
php artisan config:clear --no-interaction
php artisan package:discover --no-interaction
php artisan storage:link --force --no-interaction

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
    php artisan view:clear --no-interaction
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
fi

exec "$@"
