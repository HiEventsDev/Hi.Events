#!/bin/bash
set -e

echo "Running migrations..."
php artisan migrate --force

echo "Caching configuration..."
php artisan optimize

echo "Deployment complete."
