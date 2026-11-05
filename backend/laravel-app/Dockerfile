FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl unzip libpq-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql pgsql mbstring pcntl \
    && curl -sSL https://github.com/fullstorydev/grpcurl/releases/download/v1.9.1/grpcurl_1.9.1_linux_x86_64.tar.gz \
       | tar -xz -C /usr/local/bin grpcurl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]