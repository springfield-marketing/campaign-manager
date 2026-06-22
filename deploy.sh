#!/usr/bin/env bash
#
# Forge deploy script for Campaign Tracker.
# Paste this into the site's "Deploy Script" in Forge (it runs from the app root),
# or run it manually over SSH. Keep it in sync with docs/DEPLOYMENT.md.
#
set -euo pipefail

php artisan down --render="errors::503" --retry=15 || true

git pull origin main

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Incremental migrations only — NEVER migrate:fresh in production (see docs/DEPLOYMENT.md).
php artisan migrate --force

# Framework caches (config + routes + views + events) and Filament component/icon caches.
php artisan optimize
php artisan filament:optimize

php artisan storage:link || true

# Workers must reload to pick up the new code.
php artisan queue:restart

php artisan up
