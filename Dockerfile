FROM php:8.4-cli
RUN apt-get update && apt-get install -y --no-install-recommends git curl zip unzip libpq-dev \
 && docker-php-ext-install pdo pdo_pgsql pcntl \
 && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]