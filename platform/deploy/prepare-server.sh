#!/usr/bin/env bash
# Prepare the platform on the production server next to the legacy backend.
#
#   bash deploy/prepare-server.sh /path/to/legacy-backend
#
# Safe by design (docs/05-cutover-plan.md):
#   - never runs `php artisan migrate` (the platform owns no tables);
#   - only READS the legacy .env; never writes inside the legacy folder;
#   - users are not affected: the web server still sends every request to legacy
#     until the routing step is done separately.
set -euo pipefail

LEGACY_DIR="${1:?Usage: bash deploy/prepare-server.sh /path/to/legacy-backend}"
PLATFORM_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LEGACY_ENV="$LEGACY_DIR/.env"
MOG_PACKAGE="${MOG_PACKAGE:-$(cd "$LEGACY_DIR/.." && pwd)/mog-package}"

step() { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
fail() { printf '\033[31mERROR: %s\033[0m\n' "$1" >&2; exit 1; }

step "Checking prerequisites"
[ -f "$LEGACY_ENV" ] || fail "Legacy .env not found at $LEGACY_ENV"
[ -f "$LEGACY_DIR/artisan" ] || fail "$LEGACY_DIR does not look like the legacy Laravel backend"
case "$PLATFORM_DIR" in "$LEGACY_DIR"/*) fail "Clone the platform OUTSIDE the legacy folder";; esac
command -v php >/dev/null || fail "php not found"
command -v composer >/dev/null || fail "composer not found"
command -v npm >/dev/null || fail "npm not found"
php -m | grep -qi '^oci8$' || fail "PHP oci8 extension is not loaded (the legacy backend needs it too)"
echo "legacy:   $LEGACY_DIR"
echo "platform: $PLATFORM_DIR"
cd "$PLATFORM_DIR"

step "Installing the private SSO package (same as legacy)"
if ! composer show mog/project-helper >/dev/null 2>&1; then
    [ -d "$MOG_PACKAGE" ] || fail "mog-package not found at $MOG_PACKAGE (set MOG_PACKAGE=/path/to/mog-package)"
    composer config repositories.mog path "$MOG_PACKAGE"
    # Adds the package to composer.json/lock of this server clone only (use `git pull --autostash` later).
    composer require mog/project-helper --no-interaction
fi

step "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

step "Building the dashboard"
npm ci --no-audit --no-fund
npm run build

step "Writing .env (Oracle and SSO copied from legacy)"
if [ ! -f .env ]; then
    cp .env.example .env
fi
set_env() { # key value — replace the line or append it; the value is written verbatim
    KEY="$1" VALUE="$2" php -r '
        $file = ".env"; $key = getenv("KEY"); $line = $key."=".getenv("VALUE");
        $text = file_get_contents($file); $count = 0;
        $text = preg_replace_callback("/^".preg_quote($key, "/")."=.*$/m", fn () => $line, $text, -1, $count);
        if ($count === 0) { $text = rtrim($text, "\n")."\n".$line."\n"; }
        file_put_contents($file, $text);'
}
# Copy every Oracle / SSO line from legacy (the platform accepts the legacy DB_* names).
while IFS= read -r line; do
    key="${line%%=*}"
    set_env "$key" "${line#*=}"
done < <(grep -E '^(DB_[A-Z_]+|SSO_[A-Z_]+|MOG_SSO_[A-Z_]+|GEOSERVER_[A-Z_]+|APP_TIMEZONE|DRIVER_APP_LATEST_[A-Z_]+)=' "$LEGACY_ENV" || true)
set_env APP_ENV production
set_env APP_DEBUG false
set_env ORACLE_DRIVER oracle
set_env LEGACY_MEDIA_ROOT "\"$LEGACY_DIR/storage/app/private\""
# Owner decision (2026-11-03): drivers keep logging in with 000000 until an SMS provider exists.
grep -qE '^SMS_TEST_CODE=.+' .env || set_env SMS_TEST_CODE 000000
grep -qE '^APP_KEY=.+' .env || php artisan key:generate --force

step "Checking the Oracle connection"
php artisan tinker --execute='echo json_encode(DB::connection("oracle")->selectOne("select 1 as ok from dual")).PHP_EOL;' \
    || fail "Oracle connection failed: check the DB_* lines in .env"

step "Caching configuration (no migrations)"
php artisan config:cache
php artisan route:cache
php artisan view:cache
mkdir -p storage/framework/cache/mpdf

step "Verifying the mobile API routes"
routes="$(php artisan route:list --path=api --json | php -r 'echo count(json_decode(stream_get_contents(STDIN), true));')"
echo "mobile API routes: $routes (expected 35)"
[ "$routes" = "35" ] || fail "Unexpected number of API routes"

step "Done"
cat <<EOF
The platform is ready in $PLATFORM_DIR but receives no traffic yet.
Still to set by hand in .env if needed: MOG_PERMISSIONS_ADMIN_USER_IDS (access managers).
Make the web server user able to write: $PLATFORM_DIR/storage and $PLATFORM_DIR/bootstrap/cache
Next: route /api/valves, /api/dashboard and /api/valve-maintenance to $PLATFORM_DIR/public
(docs/05-cutover-plan.md, section 7). Never run "php artisan migrate" here.
EOF
