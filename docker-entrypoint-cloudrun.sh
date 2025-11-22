#!/bin/sh
set -e

# Wait for database connection (skip if DB_HOST is not set)
if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "" ]; then
    echo "Waiting for database connection..."
    echo "DB_HOST: $DB_HOST"
    max_attempts=30
    attempt=0
    
    # Determine if using Unix socket (Cloud SQL) or TCP connection
    if [[ "$DB_HOST" == /cloudsql/* ]]; then
        # Unix socket connection (Cloud SQL)
        DB_SOCKET="$DB_HOST"
        DB_CONNECTION_STRING="mysql:unix_socket=$DB_SOCKET;dbname=${DB_DATABASE:-}"
    else
        # TCP connection
        DB_HOST_ADDR="$DB_HOST"
        DB_PORT="${DB_PORT:-3306}"
        DB_CONNECTION_STRING="mysql:host=$DB_HOST_ADDR;port=$DB_PORT;dbname=${DB_DATABASE:-}"
    fi
    
    while [ $attempt -lt $max_attempts ]; do
        if php -r "
        try {
            \$pdo = new PDO('$DB_CONNECTION_STRING', '${DB_USERNAME}', '${DB_PASSWORD}');
            \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            \$pdo->exec('SELECT 1');
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
        " 2>/dev/null; then
            echo "Database is up!"
            break
        fi
        
        attempt=$((attempt + 1))
        if [ $((attempt % 5)) -eq 0 ]; then
            echo "Database is unavailable - attempt $attempt/$max_attempts - sleeping"
        fi
        sleep 2
    done
    
    if [ $attempt -lt $max_attempts ]; then
        # Run migrations if database is available
        echo "Running migrations..."
        php artisan migrate --force || true
    fi
fi

# Clear and optimize config for production
php artisan config:clear || true
php artisan cache:clear || true
php artisan config:cache || true

echo "Application is ready!"

exec "$@"

