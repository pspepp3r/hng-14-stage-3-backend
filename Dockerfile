FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    nginx \
    gettext-base \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ... (Previous steps: FROM, RUN apt-get, etc.)

WORKDIR /var/www/html

COPY . .

# Copy the template into the image
COPY nginx.conf.template /etc/nginx/sites-available/default.template

# Create the startup script
RUN echo "#!/bin/sh\n\
    set -e\n\
    echo \"Running migrations...\"\n\
    php bin/migrate.php\n\
    \n\
    echo \"Starting PHP-FPM and Nginx on port \$PORT...\"\n\
    \n\
    # Substitute the variable and output to sites-enabled\n\
    envsubst '\$PORT' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-enabled/default\n\
    \n\
    php-fpm --daemonize\n\
    exec nginx -g 'daemon off;'" > /usr/local/bin/start-app.sh && \
    chmod +x /usr/local/bin/start-app.sh

CMD ["/usr/local/bin/start-app.sh"]
