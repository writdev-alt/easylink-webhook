#!/bin/sh
set -e

# Wait for database connection
echo "Waiting for database connection..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if php -r "
    try {
        \$pdo = new PDO('mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306}', '${DB_USERNAME:-worker_wrpay}', '${DB_PASSWORD:-password}');
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo 'Database is available';
        exit(0);
    } catch (PDOException \$e) {
        exit(1);
    }
    " 2>/dev/null; then
        echo "Database is up!"
        break
    fi
    
    attempt=$((attempt + 1))
    echo "Database is unavailable - attempt $attempt/$max_attempts - sleeping"
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "Warning: Could not connect to database after $max_attempts attempts"
fi

# Install/update dependencies if vendor doesn't exist or in development
if [ ! -d "/app/vendor" ] || [ "${APP_ENV:-local}" = "local" ]; then
    echo "Installing/updating Composer dependencies..."
    composer install --no-interaction
fi

# Generate application key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "Generating application key..."
    php artisan key:generate --force || true
fi

# Run migrations (only if database is available)
if [ $attempt -lt $max_attempts ]; then
    echo "Running migrations..."
    php artisan migrate --force || true
fi

# Clear and cache config
php artisan config:clear || true
php artisan cache:clear || true

echo "Application is ready!"

exec "$@"

