#!/usr/bin/env bash
set -euo pipefail

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-favilla}"
DB_USER="${DB_USER:-favilla}"
export MYSQL_PWD="${DB_PASS:-}"

# --- Wait for the database to accept connections ---------------------
echo "Favilla: waiting for database ${DB_HOST}:${DB_PORT} ..."
tries=0
until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" --silent 2>/dev/null; do
    tries=$((tries + 1))
    if [ "${tries}" -ge 60 ]; then
        echo "Favilla: database not reachable after 120s, giving up." >&2
        exit 1
    fi
    sleep 2
done
echo "Favilla: database is up."

# --- Named volumes mount empty/root-owned: keep runtime dirs writable -
chown -R www-data:www-data storage public/uploads 2>/dev/null || true

# --- Optional first-run schema load (opt-in via AUTO_MIGRATE=true) ----
# Loads the consolidated schema + required seeds on an EMPTY database;
# on a populated database it only applies pending delta migrations so it
# never destroys data. Leave AUTO_MIGRATE unset to use the web installer.
if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    table_count="$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" "${DB_NAME}" \
        -N -s -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" 2>/dev/null || echo 0)"
    if [ "${table_count}" -eq 0 ]; then
        echo "Favilla: empty database → loading schema + seeds (migrate --fresh)."
        php database/migrate.php --fresh
    else
        echo "Favilla: existing database → applying pending migrations."
        php database/migrate.php
    fi
fi

exec "$@"
