#!/bin/sh
set -e

echo "Starting Love of My Life (NaluzPHP)..."

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set. Generate one with 'php naluz key:generate --show' and put it in .env." >&2
    exit 1
fi

# ── Ensure storage structure exists ───────────────────────────────────────────
# When storage/ is a named volume, the directories created at build time are hidden. Recreate them here.
cd /var/www/html
mkdir -p storage/app storage/cache storage/logs storage/sessions storage/nosql
chown -R www-data:www-data storage
chmod -R 775 storage

# Compiled views are never re-checked in production: start from a clean cache after every deploy.
rm -f storage/cache/views/* 2>/dev/null || true

echo "All done. Starting services with Supervisor..."
exec supervisord -c /etc/supervisord.conf
