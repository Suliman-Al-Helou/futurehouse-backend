FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

EXPOSE 8000

CMD php -r "echo 'DB: ' . getenv('DB_DATABASE') . PHP_EOL;" && php artisan config:clear && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}