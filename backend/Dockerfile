FROM php:8.2-fpm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libonig-dev \
    && docker-php-ext-install mbstring pdo_pgsql pgsql zip \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS build
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

FROM php-base AS runtime
WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["sh", "-c", "php artisan migrate --seed --force && exec php-fpm -F"]
