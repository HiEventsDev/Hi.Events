#!/usr/bin/env sh

set -eu

echo "[entrypoint] PORT=$PORT"

export SERVER_PORT="${PORT}"

echo "[entrypoint] SERVER_PORT=$SERVER_PORT"

envsubst '$SERVER_PORT' < "/etc/nginx/templates/port.conf.template" > "/etc/nginx/port.conf"

cp /etc/nginx/templates/nginx.conf.template /etc/nginx/nginx.conf

php-fpm -D

exec nginx -g 'daemon off;'
