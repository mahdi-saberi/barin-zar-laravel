#!/bin/sh
set -eu

: "${APP_KEY:?Set a persistent APP_KEY before starting the container}"

mkdir -p storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
chown www-data:www-data storage/app storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache

if [ "$1" = php ]; then
    exec runuser -u www-data -- "$@"
fi

exec docker-php-entrypoint "$@"
