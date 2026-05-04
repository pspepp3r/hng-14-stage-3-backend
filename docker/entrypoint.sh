#!/bin/sh
set -e

# Exit on any error
trap 'exit 1' ERR

# Run database migrations if needed
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php bin/migrate
fi

# Ensure proper permissions
if [ -d /var/www/html/storage ]; then
    chmod -R 775 /var/www/html/storage
fi

# Start Apache in foreground
exec "$@"
