#!/usr/bin/env sh
set -eu

cd "$(dirname "$0")/.."
docker compose version >/dev/null
docker network inspect traefik_proxy >/dev/null

if [ ! -f .env.production ]; then
    command -v openssl >/dev/null
    umask 077
    cp .env.production.example .env.production
    sed -i \
        -e "s|^APP_KEY=$|APP_KEY=base64:$(openssl rand -base64 32)|" \
        -e "s|^DB_PASSWORD=$|DB_PASSWORD=$(openssl rand -hex 32)|" \
        -e "s|^DB_ROOT_PASSWORD=$|DB_ROOT_PASSWORD=$(openssl rand -hex 32)|" \
        -e "s|^REVERB_APP_KEY=$|REVERB_APP_KEY=$(openssl rand -hex 16)|" \
        -e "s|^REVERB_APP_SECRET=$|REVERB_APP_SECRET=$(openssl rand -hex 32)|" \
        .env.production
    echo "Created .env.production with unique credentials. Keep this file for future deployments."
fi

docker compose --env-file .env.production -f docker-compose.production.yml config --quiet
docker compose --env-file .env.production -f docker-compose.production.yml build --pull app nginx
docker compose --env-file .env.production -f docker-compose.production.yml up -d --force-recreate --wait --wait-timeout 180 app reverb queue nginx
docker compose --env-file .env.production -f docker-compose.production.yml ps
