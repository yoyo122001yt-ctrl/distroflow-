#!/bin/bash
set -e

# Generate app key if not set
php artisan key:generate --force

# Run migrations
php artisan migrate --force

# Seed default data
php artisan db:seed --force

# Cache config & routes for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link

echo "Deployment complete!"
