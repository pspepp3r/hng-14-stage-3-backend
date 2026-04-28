FROM php:8.3-cli

# Install system dependencies (same as original)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
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

# Create the startup script
RUN echo "#!/bin/sh\n\
    set -e\n\
    \n\
    # Run migrations\n\
    echo \"Running migrations...\"\n\
    php /var/www/html/bin/migrate.php\n\
    \n\
    # Start PHP built-in server on port \$PORT, serving /public\n\
    echo \"Starting PHP server on port \$PORT...\"\n\
    exec php -S 0.0.0.0:\$PORT -t /var/www/html/public" > /usr/local/bin/start-app.sh && \
    chmod +x /usr/local/bin/start-app.sh

EXPOSE ${PORT}

CMD ["/usr/local/bin/start-app.sh"]
