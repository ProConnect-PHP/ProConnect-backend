#!/bin/sh
set -e
php artisan migrate --force
php-fpm -D
nginx -g 'daemon off;'
