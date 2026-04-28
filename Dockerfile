FROM php:8.3-apache

# Install system dependencies (same as original, plus Apache mod_rewrite)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    gettext-base \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Install PHP extensions (same as original)
RUN docker-php-ext-install pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy project files
COPY . .

# Install dependencies and fix permissions
RUN composer install --no-dev --optimize-autoloader
RUN chown -R www-data:www-data /var/www/html

# Copy Apache config template
COPY apache.conf.template /etc/apache2/sites-available/000-default.conf.template

# Disable default site, we'll use our template later
RUN rm -f /etc/apache2/sites-enabled/000-default.conf

# Create the startup script
RUN echo "#!/bin/sh\n\
    set -e\n\
    \n\
    # 1. Substitute PORT into Apache config\n\
    envsubst '\$PORT' < /etc/apache2/sites-available/000-default.conf.template > /etc/apache2/sites-available/000-default.conf\n\
    \n\
    # 2. Run migrations\n\
    echo \"Running migrations...\"\n\
    php /var/www/html/bin/migrate.php\n\
    \n\
    # 3. Start Apache (foreground)\n\
    echo \"Starting Apache on port \$PORT...\"\n\
    exec apache2-foreground" > /usr/local/bin/start-app.sh && \
    chmod +x /usr/local/bin/start-app.sh

CMD ["/usr/local/bin/start-app.sh"]    # 1. Substitute PORT into Nginx config\n\
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
