FROM php:8.3-apache

# 1. Set the DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

# 2. Update Apache configuration for the new DocumentRoot
# We use double quotes to ensure the ENV variable is expanded correctly
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf

# 3. Fix the "More than one MPM loaded" error
# We explicitly remove all enabled MPM modules and then only enable prefork
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork rewrite

# 4. Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# 5. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Copy only composer files first to leverage Docker cache
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# 7. Copy the rest of the application
COPY . .

# 8. Set up storage directories and permissions
RUN mkdir -p storage/logs storage/ratelimit \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage

# 9. Ensure Apache listens on the port provided by Railway (defaulting to 80 if not set)
# Railway usually handles this, but being explicit can help
RUN sed -i "s/Listen 80/Listen \${PORT}/g" /etc/apache2/ports.conf \
    && sed -i "s/:80/:\${PORT}/g" /etc/apache2/sites-available/000-default.conf

EXPOSE 80

# The base image already has "CMD ["apache2-foreground"]"
