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

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN mkdir -p storage/logs storage/ratelimit \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/vendor /var/www/html/public

# Configure Nginx (template)
RUN echo 'server {\n\
    listen $${PORT} default_server;\n\
    listen [::]:$${PORT} default_server;\n\
    server_name _;\n\
    root /var/www/html/public;\n\
    index index.php;\n\
    \n\
    location / {\n\
    try_files $uri $uri/ /index.php?$query_string;\n\
    }\n\
    \n\
    location ~ \\.php$ {\n\
    fastcgi_pass 127.0.0.1:9000;\n\
    fastcgi_index index.php;\n\
    include fastcgi_params;\n\
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
    }\n\
    \n\
    location ~ /\\. {\n\
    deny all;\n\
    }\n\
    }' > /etc/nginx/sites-available/default.template

# Create startup script for both PHP-FPM and Nginx
RUN echo "#!/bin/sh\n\
    set -e\n\
    \n\
    echo \"Running migrations...\"\n\
    php bin/migrate.php\n\
    \n\
    echo \"Starting PHP-FPM and Nginx on port \$PORT...\"\n\
    \n\
    # Substitute PORT variable in nginx config template\n\
    envsubst '\\$PORT' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-available/default\n\
    \n\
    # Start PHP-FPM in background\n\
    php-fpm --daemonize\n\
    \n\
    # Start Nginx in foreground\n\
    exec nginx -g 'daemon off;'" > /usr/local/bin/start-app.sh && \
    chmod +x /usr/local/bin/start-app.sh

CMD ["/usr/local/bin/start-app.sh"]
