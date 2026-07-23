#!/usr/bin/env bash
#
# Container start-up: prepare nginx, migrate the database, seed once, cache
# config, then hand off to supervisor which runs nginx + PHP-FPM.
set -euo pipefail

cd /var/www/html

# --- 1. Point nginx at the port Render gave us -----------------------------
: "${PORT:=10000}"
sed "s/__PORT__/${PORT}/g" /etc/nginx/sites-available/default > /etc/nginx/sites-enabled/default.tmp
mv /etc/nginx/sites-enabled/default.tmp /etc/nginx/sites-available/default
echo "nginx will listen on port ${PORT}"

# --- 2. Fail fast if the app key is missing --------------------------------
if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY is not set. Generate one locally with 'php artisan key:generate --show'"
    echo "and add it to the Render environment as APP_KEY."
    exit 1
fi

# --- 3. Wait for the database, then migrate --------------------------------
# TiDB/MySQL may take a few seconds to accept connections on a cold start.
echo "Waiting for the database..."
for i in $(seq 1 30); do
    if php artisan db:show >/dev/null 2>&1; then
        echo "Database is reachable."
        break
    fi
    if [ "$i" -eq 30 ]; then
        echo "FATAL: could not reach the database after 30 attempts. Check the DB_* env vars and MYSQL_ATTR_SSL_CA."
        exit 1
    fi
    sleep 2
done

echo "Running migrations..."
php artisan migrate --force

# --- 4. Seed once, only when the database is empty -------------------------
# Guarded so re-deploys don't pile up duplicate demo data. Set RUN_SEED=false
# to skip entirely.
if [ "${RUN_SEED:-true}" = "true" ]; then
    USER_COUNT="$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n1 | tr -dc '0-9')"
    if [ "${USER_COUNT:-0}" = "0" ]; then
        echo "Empty database — seeding demo data (this can take a minute on first boot)..."
        php artisan db:seed --force
    else
        echo "Database already has ${USER_COUNT} users — skipping seed."
    fi
fi

# --- 5. Make uploaded files reachable, then cache config -------------------
php artisan storage:link || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Startup complete. Handing off to supervisor."

# --- 6. Run nginx + PHP-FPM ------------------------------------------------
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
