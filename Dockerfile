# COMPOSER
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lockk ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# APACHE
FROM php:8.3-apache AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev zip unzip \
    && docker-php-ext-install -j$(nproc) pdo_mysql opache zip \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Opcache + production php.ini setting
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/opcache.ini" \
    && echo "opcache.validate_timestamps=0" >> "$PHP_INI_DIR/conf.d/opcache.ini"

# ROOTS
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}}!g' /etc/apache2/sites-enable/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# CODE
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .

#VHOST
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# PERMISSION
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# MIGRATION & START
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000
ENTRYPOINT [ "entrypoint.sh" ]
CMD [ "apache2-foreground" ]
