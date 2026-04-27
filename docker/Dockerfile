FROM dhi/php:8-alpine3.22-fpm-fips

WORKDIR /app

COPY composer.json /app/

RUN composer install

COPY . .

EXPOSE 8080

# start your server
CMD ["php", "-s", "localhost:8080"]

