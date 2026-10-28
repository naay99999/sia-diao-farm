#!/bin/sh
# Prepares the persistent storage volume and the database, then hands over to FrankenPHP.
# Every step is safe to repeat on each container start.
set -e

cd /app

mkdir -p \
    storage/app/private/photos \
    storage/database \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

if [ "$DB_CONNECTION" = "sqlite" ] && [ -n "$DB_DATABASE" ] && [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
fi

if [ ! -f storage/oauth-private.key ]; then
    php artisan passport:keys --no-interaction
fi

php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan optimize --no-interaction

exec docker-php-entrypoint "$@"
