#!/bin/bash
set -e

echo "Starting queue worker (default, webhooks)..."
php artisan queue:work \
    --sleep=3 \
    --tries=3 \
    --max-time=3600 \
    --memory=512 \
    --queue=default,webhooks
