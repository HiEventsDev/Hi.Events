FROM dunglas/frankenphp:php8.3-alpine

ENV SERVER_NAME=":8000"
ENV LARAVEL_OCTANE=1

WORKDIR /app
COPY . /app

# Combine layers and use apk directly where possible, suppressing output and avoiding the huge image layer build failures.
RUN set -ex \
    && apk add --no-cache php83-pdo_pgsql php83-pgsql php83-redis php83-pcntl php83-bcmath php83-zip php83-intl php83-opcache curl supervisor \
    && install-php-extensions gd redis pdo_pgsql sodium curl intl mbstring xml zip bcmath pcntl imagick opcache \
    && chmod -R 775 storage \
    && mkdir -p bootstrap/cache \
    && chmod -R 775 bootstrap/cache \
    && chown -R root:root storage bootstrap/cache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN composer install --no-interaction --no-dev --optimize-autoloader --prefer-dist \
    && mkdir -p /app/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer \
    && chmod -R 775 /app/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer \
    && chown -R root:root /app/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer

EXPOSE 8000
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
