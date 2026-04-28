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
RUN chown -R www-data:www-data /var/www/html

# Copy the Nginx template
COPY nginx.conf.template /etc/nginx/sites-available/default.template

# Remove the default Nginx config to prevent conflicts
RUN rm -f /etc/nginx/sites-enabled/default

# Create the startup script
RUN echo "#!/bin/sh\n\
    set -e\n\
    \n\
    # 1. Substitute PORT into Nginx config\n\
    envsubst '\$PORT' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-enabled/default\n\
    \n\
    # 2. Run migrations\n\
    echo \"Running migrations...\"\n\
    php /var/www/html/bin/migrate.php\n\
    \n\
    # 3. Start PHP-FPM and FORCE it to listen on 127.0.0.1:9000\n\
    echo \"Starting PHP-FPM...\"\n\
    php-fpm -d \"listen=127.0.0.1:9000\" --daemonize\n\
    \n\
    # 4. Wait for PHP and start Nginx\n\
    sleep 2\n\
    echo \"Starting Nginx on port \$PORT...\"\n\
    exec nginx -g 'daemon off;'" > /usr/local/bin/start-app.sh && \
    chmod +x /usr/local/bin/start-app.sh

CMD ["/usr/local/bin/start-app.sh"]
