FROM webdevops/php-nginx:8.2-alpine

WORKDIR /app

COPY . /app

ENV WEB_DOCUMENT_ROOT /app/public

ENV PHP_DISMOD=ioncube,pdo_sqlsrv,sqlsrv,pdo_mysql,mysqli,mysqlnd,pdo_sqlite,mysqlnd,mongodb,memcached

COPY ./docker/supervisord/syslog.conf /opt/docker/etc/supervisor.d/syslog.conf

RUN chown -R application:application /app \
    && chmod -R 755 /app/storage  \
    && mkdir -p /app/bootstrap/cache \
    && chmod -R 775 /app/bootstrap/cache

RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-dev \
    --optimize-autoloader \
    --prefer-dist

RUN chmod -R 777 /app/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer

EXPOSE 80 443

CMD ["supervisord"]
