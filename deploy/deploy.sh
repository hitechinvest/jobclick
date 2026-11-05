#!/usr/bin/env bash
# Деплой на VPS: ./deploy/deploy.sh  (из каталога проекта, под пользователем веб-сервера)
set -euo pipefail
cd "$(dirname "$0")/.."

php artisan down --retry=15 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
# фронтенд собран и лежит в public/build — Node.js на сервере не нужен
php artisan migrate --force
php artisan filament:assets
php artisan storage:link || true
php artisan optimize
php artisan queue:restart
php artisan up
echo "Готово"
