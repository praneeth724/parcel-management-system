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

# --- 3. Wait for the database and ensure it exists -------------------------
# TiDB Serverless can take a few seconds to wake on a cold start, and the
# target database may not exist yet. This connects WITHOUT a database name
# (so it works on a fresh cluster), creates the database if needed, and
# retries while TiDB warms up. On the final failure it prints the real error
# instead of hiding it.
echo "Connecting to the database and ensuring it exists..."
for i in $(seq 1 30); do
    if php -r '
        $host = trim((string) getenv("DB_HOST"));
        $port = trim((string) getenv("DB_PORT")) ?: "4000";
        $user = trim((string) getenv("DB_USERNAME"));
        $pass = getenv("DB_PASSWORD");
        $name = trim((string) getenv("DB_DATABASE"));
        $ca   = trim((string) getenv("MYSQL_ATTR_SSL_CA"));
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        if ($ca) { $options[PDO::MYSQL_ATTR_SSL_CA] = $ca; }
        $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, $options);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    ' 2>/tmp/db_error; then
        echo "Database is reachable and ready."
        break
    fi
    if [ "$i" -eq 30 ]; then
        echo "FATAL: could not connect to the database after 30 attempts. Real error:"
        cat /tmp/db_error
        echo "--> Check DB_HOST / DB_PORT / DB_USERNAME / DB_PASSWORD, that TLS is required (MYSQL_ATTR_SSL_CA), and that TiDB allows this connection."
        exit 1
    fi
    echo "  attempt ${i}/30 failed, retrying in 2s..."
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
