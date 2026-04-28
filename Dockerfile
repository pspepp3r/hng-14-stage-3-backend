FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql zip

RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN mkdir -p storage/logs storage/ratelimit \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/vendor /var/www/html/public

RUN echo '<VirtualHost *:${PORT}>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>\n\
    </VirtualHost>' > /etc/apache2/sites-available/000-default.conf

RUN echo '#!/bin/sh\n\
    # Update port in Apache ports.conf\n\
    echo "Listen ${PORT}" > /etc/apache2/ports.conf\n\
    \n\
    # Run migrations\n\
    echo "Running migrations..."\n\
    php bin/migrate.php\n\
    \n\
    echo "Starting Apache on port ${PORT}..."\n\
    exec apache2-foreground' > /usr/local/bin/start-app.sh && \
    chmod +x /usr/local/bin/start-app.sh

CMD ["/usr/local/bin/start-app.sh"]
