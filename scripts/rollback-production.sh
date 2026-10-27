#!/usr/bin/env bash
# ==============================================================================
# KTPL Drafting Software - Production Rollback Script
# Target: /var/www/drafting_v2
# Usage: ./scripts/rollback-production.sh [COMMIT_SHA_OR_REF]
# ==============================================================================
set -euo pipefail

DEPLOY_DIR="/var/www/drafting_v2"
APP_CONTAINER="drafting_v2-app-1"
QUEUE_CONTAINER="drafting_v2-queue-1"
REVERB_CONTAINER="drafting_v2-reverb-1"
NGINX_CONTAINER="drafting_v2-nginx-1"

ROLLBACK_TARGET="${1:-HEAD~1}"

echo -e "\033[1;33m[ROLLBACK]\033[0m Initiating rollback on $DEPLOY_DIR to target: $ROLLBACK_TARGET"

cd "$DEPLOY_DIR"

# Verify target commit exists
RESOLVED_COMMIT=$(git rev-parse "$ROLLBACK_TARGET")
echo -e "\033[1;34m[INFO]\033[0m Resolved rollback commit: $RESOLVED_COMMIT ($(git log -1 --format=%s "$RESOLVED_COMMIT"))"

# Revert Git state
git reset --hard "$RESOLVED_COMMIT"

# Clear and rebuild caches
docker exec "$APP_CONTAINER" php artisan optimize:clear
docker exec "$APP_CONTAINER" php artisan config:cache
docker exec "$APP_CONTAINER" php artisan route:cache
docker exec "$APP_CONTAINER" php artisan view:cache

# Restart services
docker restart "$QUEUE_CONTAINER" "$REVERB_CONTAINER" "$APP_CONTAINER"
docker exec "$NGINX_CONTAINER" nginx -s reload

echo -e "\033[1;32m[SUCCESS]\033[0m Rollback to $RESOLVED_COMMIT completed successfully."
