#!/usr/bin/env bash
#
# Point `current` back at the previous release (or a named one):
#   APP_DIR=/var/www/binary-system bash rollback.sh [release-directory-name]
#
# Code only: database migrations are NOT rolled back. If the release you are
# leaving ran a migration the old code cannot handle, restore the pre-deploy
# backup instead (README, "Restoring a backup").

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/binary-system}"
PHP="${PHP:-php}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
CURRENT="$APP_DIR/current"

live="$(basename "$(readlink -f "$CURRENT")")"

if [[ -n "${1:-}" ]]; then
    target="$APP_DIR/releases/$1"
else
    target="$(ls -1dt "$APP_DIR"/releases/*/ | sed 's:/$::' | grep -v "/$live\$" | head -n 1)"
fi

if [[ ! -d "$target" ]]; then
    echo "No release to roll back to (${target:-none})." >&2
    exit 1
fi

echo "[rollback] $live -> $(basename "$target")"
ln -sfn "$target" "$APP_DIR/current.next"
mv -Tf "$APP_DIR/current.next" "$CURRENT"

sudo -n systemctl reload "$PHP_FPM_SERVICE" || echo "[rollback] warning: could not reload $PHP_FPM_SERVICE"
"$PHP" "$CURRENT/artisan" queue:restart
echo "[rollback] done. The newer release is kept in releases/ until the next deploy prunes it."
