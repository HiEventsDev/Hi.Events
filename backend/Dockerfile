FROM serversideup/php:8.4-fpm-nginx-alpine

ENV PHP_OPCACHE_ENABLE=1

USER root

# Set `www-data` as the user to start FPM
RUN echo "" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
    echo "user = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
    echo "group = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf

RUN install-php-extensions intl

COPY --chown=www-data:www-data . .

RUN chmod -R 755 storage  \
    && mkdir -p bootstrap/cache \
    && chmod -R 775 bootstrap/cache \
    && mkdir -p /var/lib/nginx/tmp \
    && chown -R www-data:www-data /var/lib/nginx \
    && chmod -R 755 /var/lib/nginx

RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-dev \
    --optimize-autoloader \
    --prefer-dist

RUN mkdir -p /var/www/html/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer \
    && chmod -R 775 /var/www/html/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer \
    && chown -R www-data:www-data /var/www/html/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer

EXPOSE 8080
