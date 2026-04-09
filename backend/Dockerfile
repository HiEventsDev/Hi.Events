FROM dunglas/frankenphp:php8.3-alpine

ENV SERVER_NAME=":8000"
ENV LARAVEL_OCTANE=1

WORKDIR /app

# Install PHP extensions via FrankenPHP's extension installer (not apk php83-* packages,
# which target the system PHP rather than FrankenPHP's embedded PHP).
RUN apk add --no-cache curl \
    && install-php-extensions gd redis pdo_pgsql sodium intl mbstring xml zip bcmath pcntl imagick opcache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY . /app

RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R root:root storage bootstrap/cache

RUN composer install --no-interaction --no-dev --optimize-autoloader --prefer-dist \
    && mkdir -p /app/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer \
    && chmod -R 775 /app/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer \
    && chown -R root:root /app/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer

EXPOSE 8000
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
