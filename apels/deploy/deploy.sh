#!/bin/bash
# APELS Production Deploy Script (Req 32.1-32.2)
# Run from /var/www/apels

set -e

echo "=== APELS Deploy ==="

# Pull latest code
git pull origin main

# Install/update PHP dependencies (no dev)
composer install --no-interaction --no-dev --optimize-autoloader

# Install/update JS dependencies
npm ci

# Build front-end assets (Req 32.1)
npm run build

# Run database migrations
php artisan migrate --force --no-interaction

# Seed roles if not already seeded
php artisan db:seed --class=RoleSeeder --force --no-interaction 2>/dev/null || true

# Clear and rebuild caches (Req 32.2)
php artisan optimize

# Restart queue workers
sudo supervisorctl restart apels-worker:*

echo "=== Deploy complete ==="
