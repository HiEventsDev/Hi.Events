FROM serversideup/php:8.4-fpm-nginx-alpine

ENV PHP_OPCACHE_ENABLE=1

USER root

# Set `www-data` as the user to start FPM
RUN echo "" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
    echo "user = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
    echo "group = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf

RUN install-php-extensions intl

RUN apk add --no-cache gettext

COPY nginx.conf.template /etc/nginx/templates/nginx.conf.template
COPY port.conf.template /etc/nginx/templates/port.conf.template

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY --chown=www-data:www-data . .

RUN chmod -R 755 storage  \
    && touch storage/logs/laravel.log \
    && chown -R www-data:www-data storage/logs/laravel.log \
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

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

CMD ["nginx", "-g", "daemon off;"]
