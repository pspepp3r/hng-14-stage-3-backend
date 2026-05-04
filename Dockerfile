FROM php:8.3-apache

# 1. Update Apache DocumentRoot to point to the public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# 2. Enable Apache mod_rewrite (essential for framework routing)
RUN a2enmod rewrite

# 3. Standard setup (extensions, composer, etc.)
RUN docker-php-ext-install pdo_mysql
WORKDIR /var/www/html
COPY . .

# If you use composer, copy the binary or use multi-stage
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader
