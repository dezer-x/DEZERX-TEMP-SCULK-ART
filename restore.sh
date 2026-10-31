#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/spartan}"
BASE="https://raw.githubusercontent.com/dezer-x/DEZERX-TEMP-SCULK-ART/main"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

[[ -f "${APP_DIR}/artisan" ]] || { echo "No Spartan installation in ${APP_DIR} (set APP_DIR=...)." >&2; exit 1; }

curl -fsSL -o "${WORK}/restore.php" "${BASE}/restore.php"
curl -fsSL -o "${WORK}/map.php" "${BASE}/map.php"

cd "$APP_DIR"
php "${WORK}/restore.php" "$APP_DIR" "$BASE" "${WORK}/map.php"

if [[ "${DRY_RUN:-0}" != 1 ]]; then
    chown -R --reference="${APP_DIR}/storage/app/public" "${APP_DIR}/storage/app/public"
    php artisan cache:clear >/dev/null
    systemctl reload dezerx-octane 2>/dev/null || true
    echo "Ownership fixed, cache cleared, Octane reloaded."
fi
