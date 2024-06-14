FROM node:alpine AS node-frontend

WORKDIR /app/frontend

ENV PHP_OPCACHE_ENABLE=1

RUN apk add --no-cache yarn

COPY ./frontend/package.json ./frontend/yarn.lock ./

COPY ./frontend .

RUN yarn install && yarn build

FROM serversideup/php:beta-8.3.2-fpm-alpine

RUN install-php-extensions intl

RUN apk add --no-cache nodejs yarn nginx supervisor

COPY --from=node-frontend /app/frontend /app/frontend

COPY ./backend /app/backend
RUN chown -R www-data:www-data /app/backend \
    && find /app/backend -type d -exec chmod 755 {} \; \
    && find /app/backend -type f -exec chmod 644 {} \; \
    && chmod -R 777 /app/backend/storage /app/backend/bootstrap/cache \
    && composer install --working-dir=/app/backend \
        --ignore-platform-reqs \
        --no-interaction \
        --no-dev \
        --optimize-autoloader \
        --prefer-dist \
    && chmod -R 777 /app/backend/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer

COPY ./docker/all-in-one/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/all-in-one/supervisor/supervisord.conf /etc/supervisord.conf

COPY ./docker/all-in-one/scripts/startup.sh /startup.sh
RUN dos2unix /startup.sh && chmod +x /startup.sh

EXPOSE 80

WORKDIR /app

CMD ["/startup.sh"]
