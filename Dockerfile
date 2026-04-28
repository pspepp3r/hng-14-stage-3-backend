# Use the official PHP 8.3 Apache image
FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql zip

# Enable Apache mod_rewrite for routing
RUN a2enmod rewrite

# Set Apache DocumentRoot to the public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Ensure the public directory exists before creating .htaccess
RUN mkdir -p /var/www/html/public

# Create a basic .htaccess to route all requests to index.php if not present
RUN echo '<IfModule mod_rewrite.c>\n\
    RewriteEngine On\n\
    RewriteCond %{REQUEST_FILENAME} !-f\n\
    RewriteCond %{REQUEST_FILENAME} !-d\n\
    RewriteRule . index.php [L]\n\
</IfModule>' > /var/www/html/public/.htaccess

# Use the production php.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Copy the application code
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions for storage and logs
RUN mkdir -p storage/logs storage/ratelimit && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/vendor

# Railway uses a dynamic $PORT environment variable
# This script ensures Apache listens on the correct port and runs migrations at startup
RUN echo '#!/bin/sh\n\
sed -i "s/80/${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf\n\
# Run migrations before starting the server\n\
php bin/migrate.php\n\
exec apache2-foreground' > /usr/local/bin/start-app.sh && \
    chmod +x /usr/local/bin/start-app.sh

# Start the application
CMD ["/usr/local/bin/start-app.sh"]
