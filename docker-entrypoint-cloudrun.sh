#!/bin/sh
set -e

echo "Starting application..."

# Create empty .env file if it doesn't exist (Laravel expects it)
# Cloud Run uses environment variables directly, but Laravel still tries to load .env
if [ ! -f /app/.env ]; then
    echo "Creating empty .env file for Laravel..."
    touch /app/.env
    chmod 644 /app/.env
fi

# Clear config cache (if exists) to allow env variables to be used
php artisan config:clear || true
php artisan cache:clear || true

# Quick database connection check (non-blocking for Cloud Run)
if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "" ]; then
    echo "Checking database connection..."
    echo "DB_HOST: $DB_HOST"
    
    # Determine if using Unix socket (Cloud SQL) or TCP connection
    if echo "$DB_HOST" | grep -q "^/cloudsql/"; then
        # Unix socket connection (Cloud SQL)
        DB_SOCKET="$DB_HOST"
        DB_CONNECTION_STRING="mysql:unix_socket=$DB_SOCKET;dbname=${DB_DATABASE:-}"
    else
        # TCP connection
        DB_HOST_ADDR="$DB_HOST"
        DB_PORT="${DB_PORT:-3306}"
        DB_CONNECTION_STRING="mysql:host=$DB_HOST_ADDR;port=$DB_PORT;dbname=${DB_DATABASE:-}"
    fi
    
    # Quick check (max 10 seconds for Cloud Run)
    if php -r "
    try {
        \$pdo = new PDO('$DB_CONNECTION_STRING', '${DB_USERNAME}', '${DB_PASSWORD}', [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        \$pdo->exec('SELECT 1');
        exit(0);
    } catch (Exception \$e) {
        exit(1);
    }
    " 2>/dev/null; then
        echo "Database connection verified!"
        # Run migrations if database is available
        echo "Running migrations..."
        php artisan migrate --force || true
    else
        echo "Warning: Database connection check failed. Continuing anyway..."
        echo "The application will retry database connections on first request."
    fi
fi

echo "Application is ready!"

# Execute the command passed as arguments (start-octane.sh)
exec "$@"

