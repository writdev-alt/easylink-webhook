# syntax=docker/dockerfile:1.5

# Fallback base: PHP 8.4 CLI (avoid GHCR)
FROM php:8.4-cli

# Install system deps and PHP extensions needed by Laravel
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ca-certificates \
    unzip \
    git \
    libzip-dev \
    libicu-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    libhiredis-dev \
&& docker-php-ext-configure gd --with-freetype --with-jpeg \
&& docker-php-ext-install pdo_mysql bcmath intl zip opcache gd \
&& pecl install redis \
&& docker-php-ext-enable redis \
&& rm -rf /var/lib/apt/lists/*

# Add Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies (optimized for production)
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

# Copy application source
COPY . .

# Set correct permissions for Laravel writable directories
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Clear package discovery cache to remove references to dev packages
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php 2>/dev/null || true

# Generate optimized autoloader (skip scripts to avoid Laravel bootstrap issues)
RUN composer dump-autoload --optimize --classmap-authoritative --no-scripts

# Create startup script for Laravel optimization
RUN echo '#!/bin/sh\n\
set -e\n\
\n\
# Ensure storage directories exist\n\
mkdir -p storage/framework/cache/data\n\
mkdir -p storage/framework/sessions\n\
mkdir -p storage/framework/views\n\
mkdir -p storage/logs\n\
mkdir -p bootstrap/cache\n\
\n\
# Set permissions\n\
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true\n\
chmod -R 775 storage bootstrap/cache\n\
\n\
# Clear caches (ignore errors if config doesn'\''t exist yet)\n\
php artisan config:clear 2>/dev/null || true\n\
php artisan cache:clear 2>/dev/null || true\n\
php artisan route:clear 2>/dev/null || true\n\
php artisan view:clear 2>/dev/null || true\n\
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php 2>/dev/null || true\n\
\n\
# Optimize Laravel for production (these may fail if DB is not available, but that'\''s OK)\n\
# We'\''ll cache config, routes, and views which don'\''t require DB connection\n\
php artisan config:cache 2>/dev/null || echo "Config cache skipped (may need DB connection)"\n\
php artisan route:cache 2>/dev/null || echo "Route cache skipped (may need DB connection)"\n\
php artisan view:cache 2>/dev/null || echo "View cache skipped"\n\
\n\
# Start PHP built-in server\n\
exec php -S 0.0.0.0:${PORT:-8080} -t public public/index.php\n\
' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Environment defaults (can be overridden)
# Cloud Run uses PORT=8080 by default, but will override this
ENV APP_ENV=production \
    APP_DEBUG=false \
    OCTANE_PORT=8080 \
    PORT=8080

EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
  CMD curl -f -H "X-Health-Check-Key: ${HEALTH_CHECK_API_KEY:-}" http://localhost:${PORT:-80}/up || exit 1

# Start using startup script
CMD ["/usr/local/bin/start.sh"]
