FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    nginx \
    gettext-base \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy project files
COPY . .

# Install dependencies and fix permissions
RUN composer install --no-dev --optimize-autoloader
RUN mkdir -p storage/logs storage/ratelimit \
    && chown -R www-data:www-data /var/www/html

# Copy the Nginx template
COPY nginx.conf.template /etc/nginx/sites-available/default.template

# Ensure PHP-FPM listens on port 9000 (Prevents 502 error)
RUN sed -i 's|listen = /run/php/php8.3-fpm.sock|listen = 9000|g' /usr/local/etc/php-fpm.d/www.conf

# Create the startup script
RUN echo "#!/bin/sh\n\
    set -e\n\
    \n\
    # 1. Substitute PORT into Nginx config\n\
    envsubst '\$PORT' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-enabled/default\n\
    \n\
    # 2. Run migrations (using absolute path for safety)\n\
    echo \"Running migrations...\"\n\
    php /var/www/html/bin/migrate.php\n\
    \n\
    # 3. Start PHP-FPM in background\n\
    echo \"Starting PHP-FPM...\"\n\
    php-fpm --daemonize\n\
    \n\
    # 4. Give PHP a second to start, then start Nginx\n\
    sleep 2\n\
    echo \"Starting Nginx on port \$PORT...\"\n\
    exec nginx -g 'daemon off;'" > /usr/local/bin/start-app.sh && \
    chmod +x /usr/local/bin/start-app.sh

CMD ["/usr/local/bin/start-app.sh"]
