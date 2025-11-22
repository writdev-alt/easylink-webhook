#!/bin/sh
set -e

# Get PORT from environment variable (Cloud Run provides this)
PORT=${PORT:-8080}
ADMIN_PORT=$((PORT + 1))

echo "Starting Laravel Octane on port $PORT with admin port $ADMIN_PORT"

# Start Laravel Octane with FrankenPHP
exec php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=$PORT \
    --admin-port=$ADMIN_PORT

