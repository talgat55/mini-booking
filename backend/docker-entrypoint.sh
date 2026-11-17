#!/bin/sh
set -eu
if [ ! -f .env ]; then cp .env.example .env; fi
if ! grep -q '^APP_KEY=base64:' .env; then php artisan key:generate --force; fi
mkdir -p storage/app
[ -f storage/app/database.sqlite ] || touch storage/app/database.sqlite
php artisan migrate --force
php artisan db:seed --force
exec "$@"
