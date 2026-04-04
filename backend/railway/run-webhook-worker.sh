#!/bin/bash
set -e

echo "Starting webhook queue worker..."
php artisan queue:work \
    --sleep=3 \
    --tries=3 \
    --max-time=3600 \
    --memory=512 \
    --queue=webhooks
