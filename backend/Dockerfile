FROM composer:2 AS composer
FROM php:8.3-cli-alpine
RUN apk add --no-cache sqlite-dev oniguruma-dev libxml2-dev git unzip \
    && docker-php-ext-install pdo_sqlite mbstring dom xml xmlwriter
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY . .
RUN composer dump-autoload --optimize && chmod +x docker-entrypoint.sh
EXPOSE 8000
ENTRYPOINT ["./docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
