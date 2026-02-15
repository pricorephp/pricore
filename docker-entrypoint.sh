#!/bin/sh
set -e

APP_KEY_FILE="/app/storage/app_key"

# Auto-generate APP_KEY if not provided via environment
if [ -z "$APP_KEY" ]; then
    if [ -f "$APP_KEY_FILE" ]; then
        export APP_KEY=$(cat "$APP_KEY_FILE")
        echo "[entrypoint] Loaded APP_KEY from $APP_KEY_FILE"
    else
        export APP_KEY=$(php artisan key:generate --show)
        mkdir -p "$(dirname "$APP_KEY_FILE")"
        echo "$APP_KEY" > "$APP_KEY_FILE"
        echo "[entrypoint] Generated new APP_KEY and saved to $APP_KEY_FILE"
    fi
else
    echo "[entrypoint] Using APP_KEY from environment"
fi

# Create SQLite database file if it doesn't exist
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_PATH="${DB_DATABASE:-/app/database/database.sqlite}"
    if [ ! -f "$DB_PATH" ]; then
        mkdir -p "$(dirname "$DB_PATH")"
        touch "$DB_PATH"
        echo "[entrypoint] Created SQLite database at $DB_PATH"
    fi
fi

# Only run migrations and cache warming when the main process is frankenphp
# This prevents horizon/scheduler containers from racing to migrate
if echo "$1" | grep -q "frankenphp"; then
    echo "[entrypoint] Running database migrations..."
    php artisan migrate --force --no-interaction

    echo "[entrypoint] Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"
