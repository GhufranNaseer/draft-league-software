#!/usr/bin/env bash
# ==============================================================================
# KTPL Drafting Software - Production Deployment Script
# Target: /var/www/drafting_v2
# Usage: ./scripts/deploy-production.sh [COMMIT_SHA]
# ==============================================================================
set -euo pipefail

# Configuration
DEPLOY_DIR="/var/www/drafting_v2"
BACKUP_BASE_DIR="/var/backups/drafting_v2"
APP_CONTAINER="drafting_v2-app-1"
DB_CONTAINER="drafting_v2-database-1"
QUEUE_CONTAINER="drafting_v2-queue-1"
REVERB_CONTAINER="drafting_v2-reverb-1"
NGINX_CONTAINER="drafting_v2-nginx-1"
COMPOSE_FILE="docker-compose.production.yml"
LOCAL_NGINX_URL="http://127.0.0.1:8007/login"
HOST_HTTPS_URL="https://127.0.0.1:8443/login"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TARGET_COMMIT="${1:-HEAD}"
PREV_COMMIT=""

# Logging helpers
log_info() {
    echo -e "\033[1;34m[INFO]\033[0m $(date +"%Y-%m-%d %H:%M:%S") - $1"
}

log_success() {
    echo -e "\033[1;32m[SUCCESS]\033[0m $(date +"%Y-%m-%d %H:%M:%S") - $1"
}

log_warn() {
    echo -e "\033[1;33m[WARN]\033[0m $(date +"%Y-%m-%d %H:%M:%S") - $1"
}

log_error() {
    echo -e "\033[1;31m[ERROR]\033[0m $(date +"%Y-%m-%d %H:%M:%S") - $1" >&2
}

# Cleanup on failure
rollback() {
    log_error "Deployment failed! Initiating rollback..."
    if [ -n "$PREV_COMMIT" ]; then
        log_warn "Restoring previous Git commit: $PREV_COMMIT"
        git reset --hard "$PREV_COMMIT" || true
        log_warn "Re-clearing caches..."
        docker exec "$APP_CONTAINER" php artisan optimize:clear || true
        docker restart "$QUEUE_CONTAINER" "$REVERB_CONTAINER" "$APP_CONTAINER" || true
        log_warn "Rollback to $PREV_COMMIT completed. Database was NOT modified."
    fi
    exit 1
}

trap rollback ERR

# ------------------------------------------------------------------------------
# 1. Pre-flight environment validation
# ------------------------------------------------------------------------------
log_info "Starting KTPL Production Deployment (Timestamp: $TIMESTAMP)"

if [ "$(pwd)" != "$DEPLOY_DIR" ]; then
    log_info "Switching working directory to $DEPLOY_DIR"
    cd "$DEPLOY_DIR"
fi

if [ ! -f "$COMPOSE_FILE" ]; then
    log_error "Compose file $COMPOSE_FILE not found in $DEPLOY_DIR"
    exit 1
fi

if [ ! -f ".env.production" ]; then
    log_error "Production environment file .env.production not found!"
    exit 1
fi

for cmd in git docker docker-compose; do
    if ! command -v "$cmd" &>/dev/null && ! docker compose version &>/dev/null; then
        log_error "Required tool '$cmd' is not available."
        exit 1
    fi
done

PREV_COMMIT=$(git rev-parse HEAD)
log_info "Current active commit: $PREV_COMMIT"
log_info "Target deployment commit/ref: $TARGET_COMMIT"

# ------------------------------------------------------------------------------
# 2. Database Backup Verification
# ------------------------------------------------------------------------------
log_info "Creating pre-deployment database dump..."
mkdir -p "$BACKUP_BASE_DIR"
chmod 700 "$BACKUP_BASE_DIR"

DB_BACKUP_FILE="$BACKUP_BASE_DIR/db_pre_deploy_${TIMESTAMP}.sql"
DB_PASS=$(grep '^DB_PASSWORD=' .env.production | cut -d'=' -f2-)
DB_USER=$(grep '^DB_USERNAME=' .env.production | cut -d'=' -f2- || echo "drafting")
DB_NAME=$(grep '^DB_DATABASE=' .env.production | cut -d'=' -f2- || echo "drafting")

if [ -z "$DB_PASS" ]; then
    log_error "DB_PASSWORD could not be retrieved from .env.production"
    exit 1
fi

docker exec "$DB_CONTAINER" mariadb-dump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$DB_BACKUP_FILE"
chmod 600 "$DB_BACKUP_FILE"

if [ ! -s "$DB_BACKUP_FILE" ]; then
    log_error "Database dump failed or produced an empty file: $DB_BACKUP_FILE"
    exit 1
fi

DUMP_SIZE=$(ls -lh "$DB_BACKUP_FILE" | awk '{print $5}')
log_success "Database dump verified ($DUMP_SIZE): $DB_BACKUP_FILE"

# Keep only the 10 most recent backups
find "$BACKUP_BASE_DIR" -maxdepth 1 -name "db_pre_deploy_*.sql" -type f | sort -r | tail -n +11 | xargs -r rm -f

# ------------------------------------------------------------------------------
# 3. Source Code Synchronization
# ------------------------------------------------------------------------------
log_info "Fetching latest commits from origin..."
git fetch origin main

if [ "$TARGET_COMMIT" = "HEAD" ] || [ "$TARGET_COMMIT" = "origin/main" ]; then
    RESOLVED_COMMIT=$(git rev-parse origin/main)
else
    RESOLVED_COMMIT=$(git rev-parse "$TARGET_COMMIT")
fi

log_info "Checking out release commit: $RESOLVED_COMMIT"
git reset --hard "$RESOLVED_COMMIT"

# ------------------------------------------------------------------------------
# 4. Frontend Asset Verification
# ------------------------------------------------------------------------------
log_info "Verifying frontend build assets..."
if [ ! -f "public/build/manifest.json" ]; then
    log_error "public/build/manifest.json is missing! Assets must be synced before running deployment script."
    exit 1
fi

MANIFEST_COUNT=$(grep -c 'file' public/build/manifest.json || true)
log_success "Frontend manifest verified ($MANIFEST_COUNT entries found)."

# ------------------------------------------------------------------------------
# 5. File Permissions & Directory Setup
# ------------------------------------------------------------------------------
log_info "Verifying storage and cache permissions..."
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

# ------------------------------------------------------------------------------
# 6. Laravel Cache & Optimization Lifecycle
# ------------------------------------------------------------------------------
log_info "Rebuilding Laravel production caches..."
docker exec "$APP_CONTAINER" php artisan optimize:clear
docker exec "$APP_CONTAINER" php artisan config:cache
docker exec "$APP_CONTAINER" php artisan route:cache
docker exec "$APP_CONTAINER" php artisan view:cache
log_success "Laravel caches compiled successfully."

# ------------------------------------------------------------------------------
# 7. Background Services & Container Reload
# ------------------------------------------------------------------------------
log_info "Restarting background queue and Reverb WebSocket services..."
docker restart "$QUEUE_CONTAINER"
docker restart "$REVERB_CONTAINER"

log_info "Reloading PHP-FPM / App container..."
docker restart "$APP_CONTAINER"

log_info "Testing and reloading container Nginx..."
docker exec "$NGINX_CONTAINER" nginx -t
docker exec "$NGINX_CONTAINER" nginx -s reload

# ------------------------------------------------------------------------------
# 8. Post-Deployment Health Verification
# ------------------------------------------------------------------------------
log_info "Running post-deployment health verification..."

# Wait 5 seconds for services to settle
sleep 5

# Check container health status
for c in "$APP_CONTAINER" "$DB_CONTAINER" "$REVERB_CONTAINER"; do
    STATUS=$(docker inspect --format='{{.State.Health.Status}}' "$c" 2>/dev/null || echo "no-healthcheck")
    if [ "$STATUS" != "healthy" ] && [ "$STATUS" != "no-healthcheck" ]; then
        log_error "Container $c is not healthy! Current status: $STATUS"
        exit 1
    fi
    log_success "Container $c is healthy."
done

# Test container Nginx upstream
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$LOCAL_NGINX_URL" || true)
if [ "$HTTP_STATUS" != "200" ] && [ "$HTTP_STATUS" != "302" ]; then
    log_error "Local Nginx health check failed! URL: $LOCAL_NGINX_URL returned HTTP $HTTP_STATUS"
    exit 1
fi
log_success "Local Nginx returned HTTP $HTTP_STATUS on $LOCAL_NGINX_URL."

# Test Host Nginx over HTTPS (ignoring self-signed/IP cert warning for health check only)
HOST_HTTP_STATUS=$(curl -k -s -o /dev/null -w "%{http_code}" "$HOST_HTTPS_URL" || true)
if [ "$HOST_HTTP_STATUS" != "200" ] && [ "$HOST_HTTP_STATUS" != "302" ]; then
    log_warn "Host HTTPS check returned HTTP $HOST_HTTP_STATUS on $HOST_HTTPS_URL."
else
    log_success "Host HTTPS endpoint verified: HTTP $HOST_HTTP_STATUS."
fi

# Test Reverb WebSocket port accessibility
if docker exec "$APP_CONTAINER" php -r "exit(@fsockopen('reverb', 8080, \$errno, \$errstr, 3) ? 0 : 1);"; then
    log_success "Reverb WebSocket service is responding on reverb:8080."
else
    log_error "Reverb WebSocket port is not responding!"
    exit 1
fi

log_success "=================================================================="
log_success "DEPLOYMENT COMPLETED SUCCESSFULLY"
log_success "Deployed Commit: $(git rev-parse --short HEAD) - $(git log -1 --format=%s)"
log_success "=================================================================="
