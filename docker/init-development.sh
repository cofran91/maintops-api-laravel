#!/bin/sh

set -eu

if [ ! -f .env ]; then
    cp .env.example .env
fi

composer install --no-interaction --prefer-dist
php artisan key:generate --force
php artisan migrate --force
