#!/bin/bash
set -e

# Function to run Laravel setup tasks
setup_laravel() {
    echo "Setting up Laravel application..."

    # Install dependencies
    if [ -f "composer.json" ]; then
        composer install --no-interaction --optimize-autoloader
    fi

    # Generate app key if not set
#    if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:replace-with-your-key" ]; then
#        php artisan key:generate
#    fi

    # Run migrations if env variable set
    if [ "${RUN_MIGRATIONS}" = "true" ]; then
        echo "Running database migrations..."
        php artisan migrate --force
    fi

    # Clear cache if needed
    if [ "${CLEAR_CACHE}" = "true" ]; then
        echo "Clearing application cache..."
        php artisan cache:clear
        php artisan config:clear
        php artisan view:clear
        php artisan route:clear
    fi

    # Cache if in production
    if [ "$APP_ENV" = "production" ]; then
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
    fi
}

# Switch to root to handle file permissions and services
cd /var/www/html

# Set proper permissions (needs root)
if [ "$FIX_PERMISSIONS" = "true" ]; then
    echo "Fixing permissions..."
    chown -R www:www /var/www/html
    chmod -R 755 /var/www/html
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
fi

# Execute Laravel setup as www user
if [ "$SKIP_LARAVEL_SETUP" != "true" ]; then
    setup_laravel;
fi

# Run custom command if provided
if [ $# -gt 0 ]; then
    exec "$@"
fi

# Start services with supervisor (needs to run as root)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
