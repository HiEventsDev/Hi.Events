FROM node:22-alpine AS node-frontend

WORKDIR /app/frontend

RUN apk add --no-cache yarn

# Increase network timeout for slow ARM emulation builds
RUN yarn config set network-timeout 600000

COPY ./frontend/package.json ./frontend/yarn.lock ./

COPY ./frontend .

RUN yarn install --network-timeout 600000 --frozen-lockfile && yarn build

# Use stable multi-arch serversideup/php image
FROM serversideup/php:8.3-fpm-alpine

ENV PHP_OPCACHE_ENABLE=1

# Switch to root for installing extensions and packages
USER root

RUN install-php-extensions intl

RUN apk add --no-cache nodejs yarn nginx supervisor dos2unix

COPY --from=node-frontend /app/frontend /app/frontend

COPY ./backend /app/backend
RUN mkdir -p /app/backend/bootstrap/cache \
    && mkdir -p /app/backend/storage \
    && chown -R www-data:www-data /app/backend \
    && find /app/backend -type d -exec chmod 755 {} \; \
    && find /app/backend -type f -exec chmod 644 {} \; \
    && chmod -R 755 /app/backend/storage /app/backend/bootstrap/cache \
    && composer install --working-dir=/app/backend \
        --ignore-platform-reqs \
        --no-interaction \
        --no-dev \
        --optimize-autoloader \
        --prefer-dist \
    && chmod -R 755 /app/backend/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer

COPY ./docker/all-in-one/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/all-in-one/supervisor/supervisord.conf /etc/supervisord.conf

COPY ./docker/all-in-one/scripts/startup.sh /startup.sh
RUN dos2unix /startup.sh && chmod +x /startup.sh

EXPOSE 80

WORKDIR /app

CMD ["/startup.sh"]
