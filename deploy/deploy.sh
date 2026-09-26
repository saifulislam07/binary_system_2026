#!/usr/bin/env bash
#
# Zero-downtime deploy for the Binary Business Management System.
#
#   releases/<timestamp>/   one directory per deploy (code + vendor + built assets)
#   shared/.env             production settings, never in git
#   shared/storage/         uploads (KYC documents), logs, sessions, backups
#   current -> releases/…   the live release; nginx, cron and supervisor use it
#
# Each deploy builds a new release next to the live one, and only switches the
# `current` symlink (atomically) once everything succeeded. If migrations are
# pending, the site shows the maintenance page while they run; otherwise users
# never notice.
#
# Usage (on the server, as the deploy user):
#   APP_DIR=/var/www/binary-system bash deploy.sh <branch|tag|commit>
# or from CI:  ssh deploy@host "APP_DIR=… bash -s -- <ref>" < deploy/deploy.sh
#
# Settings (environment variables):
#   APP_DIR          install root                 (default /var/www/binary-system)
#   REPO             git URL the server can read  (default the GitHub repo over SSH)
#   PHP              php binary                   (default php)
#   PHP_FPM_SERVICE  reloaded to reset OPcache    (default php8.3-fpm; needs a sudoers rule)
#   KEEP_RELEASES    releases kept for rollback   (default 5)

set -Eeuo pipefail

REF="${1:-main}"
APP_DIR="${APP_DIR:-/var/www/binary-system}"
REPO="${REPO:-git@github.com:saifulislam07/binary_system_2026.git}"
PHP="${PHP:-php}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"

SHARED="$APP_DIR/shared"
CURRENT="$APP_DIR/current"
MIRROR="$APP_DIR/repo.git"
RELEASE="$APP_DIR/releases/$(date +%Y%m%d%H%M%S)"
WENT_DOWN=0

log() { printf '\n\033[1;34m[deploy]\033[0m %s\n' "$*"; }

if [[ ! "$REF" =~ ^[A-Za-z0-9._/-]+$ ]]; then
    echo "Refusing suspicious ref: $REF" >&2
    exit 1
fi

on_error() {
    echo "[deploy] FAILED — the live release was not switched." >&2
    if [[ $WENT_DOWN -eq 1 && -L "$CURRENT" ]]; then
        "$PHP" "$CURRENT/artisan" up || true
    fi
    rm -rf "$RELEASE"
}
trap on_error ERR

# ---------------------------------------------------------------- first run
mkdir -p "$APP_DIR/releases" \
    "$SHARED/storage/app/public" "$SHARED/storage/app/private" "$SHARED/storage/app/backups" \
    "$SHARED/storage/framework/cache/data" "$SHARED/storage/framework/sessions" "$SHARED/storage/framework/views" \
    "$SHARED/storage/logs"

if [[ ! -f "$SHARED/.env" ]]; then
    echo "Missing $SHARED/.env — copy .env.example there and fill it in (see README, 'Production .env')." >&2
    exit 1
fi

# ---------------------------------------------------------------- code
log "Fetching $REF"
if [[ ! -d "$MIRROR" ]]; then
    git clone --quiet --mirror "$REPO" "$MIRROR"
fi
git -C "$MIRROR" remote update --prune >/dev/null
COMMIT="$(git -C "$MIRROR" rev-parse --verify "$REF^{commit}")"

mkdir -p "$RELEASE"
git -C "$MIRROR" archive "$COMMIT" | tar -x -C "$RELEASE"
echo "$COMMIT" > "$RELEASE/REVISION"
log "Building release $(basename "$RELEASE") at ${COMMIT:0:12}"

rm -rf "$RELEASE/storage"
ln -s "$SHARED/storage" "$RELEASE/storage"
ln -s "$SHARED/.env" "$RELEASE/.env"

cd "$RELEASE"

# ---------------------------------------------------------------- build
composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
"$PHP" artisan adminlte:install --only=assets --only=vendor_assets --force --no-interaction
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules
"$PHP" artisan storage:link --relative --force

# Caches are built inside the new release, before it goes live.
"$PHP" artisan optimize

# ---------------------------------------------------------------- database
if "$PHP" artisan migrate:status --pending --no-ansi | grep -q 'Pending'; then
    log "Pending migrations — maintenance mode while they run"
    if [[ -L "$CURRENT" ]]; then
        "$PHP" "$CURRENT/artisan" down --retry=15 --refresh=15
        WENT_DOWN=1
    fi
    "$PHP" artisan migrate --force
fi

# Roles, permissions, packages, rules, ranks: idempotent, never overwrites admin edits.
"$PHP" artisan db:seed --class='Database\Seeders\ReferenceDataSeeder' --force

# ---------------------------------------------------------------- go live
log "Switching current -> $(basename "$RELEASE")"
ln -sfn "$RELEASE" "$APP_DIR/current.next"
mv -Tf "$APP_DIR/current.next" "$CURRENT"

# New code in PHP-FPM (OPcache) and in the queue workers (they finish their job, supervisor restarts them).
sudo -n systemctl reload "$PHP_FPM_SERVICE" || echo "[deploy] warning: could not reload $PHP_FPM_SERVICE (add the sudoers rule from the README)"
"$PHP" "$CURRENT/artisan" queue:restart

if [[ $WENT_DOWN -eq 1 ]]; then
    "$PHP" "$CURRENT/artisan" up
    WENT_DOWN=0
fi

trap - ERR

# ---------------------------------------------------------------- tidy up
log "Keeping the last $KEEP_RELEASES releases"
ls -1dt "$APP_DIR"/releases/*/ | tail -n +"$((KEEP_RELEASES + 1))" | xargs -r rm -rf

log "Deployed ${COMMIT:0:12}. Check: curl -fsS \$(grep ^APP_URL $SHARED/.env | cut -d= -f2)/up"
