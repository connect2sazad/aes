#!/bin/sh
set -eu
if [ "$(id -u)" = "0" ]; then
    mkdir -p /var/www/html/storage/photos /var/www/html/storage/sessions
    chown www-data:www-data /var/www/html/storage /var/www/html/storage/photos /var/www/html/storage/sessions
    chmod 700 /var/www/html/storage/photos /var/www/html/storage/sessions
fi
exec docker-php-entrypoint "$@"
