#!/bin/bash
set -e

echo "Starting scheduler..."

while true; do
    php artisan schedule:run --verbose --no-interaction &
    sleep 60
done
