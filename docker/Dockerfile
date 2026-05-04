# Multi-stage build for PHP application
# Stage 1: Composer dependencies
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

# Stage 2: Runtime environment
FROM php:8.3-apache

# Install system dependencies with specific versions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    opcache \
    zip

# Enable Apache modules
RUN a2enmod rewrite && \
    a2enmod headers && \
    a2enmod http2

# Copy production PHP configuration
COPY php.ini-production "$PHP_INI_DIR/php.ini"

# Configure Opcache for production
RUN echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" && \
    echo "opcache.validate_timestamps=0" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" && \
    echo "opcache.max_accelerated_files=10000" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" && \
    echo "opcache.memory_consumption=256" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini" && \
    echo "opcache.interned_strings_buffer=16" >> "$PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini"

# Configure Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-enabled/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working directory
WORKDIR /var/www/html

# Copy vendor from builder stage
COPY --from=vendor /app/vendor ./vendor

# Copy application code
COPY . .

# Copy Apache VirtualHost configuration
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Create non-root user for security
RUN useradd -m -u 1000 appuser && \
    chown -R appuser:appuser /var/www/html && \
    chmod -R 755 /var/www/html && \
    find /var/www/html/storage -type d -exec chmod 775 {} \; && \
    find /var/www/html/storage -type f -exec chmod 664 {} \;

# Setup entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/api/me || exit 1

# Expose port
EXPOSE 8000

# Set environment defaults
ENV PORT=8000 \
    APACHE_LOG_DIR=/var/log/apache2

# Run as non-root user (optional, keep as root for Apache if needed)
# USER appuser

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
