#!/bin/sh
set -eu

config_dir=/run/barinzar-config
if [ -f "$config_dir/.env" ]; then
    ln -sfn "$config_dir/.env" .env
elif [ -z "${APP_KEY:-}" ] && [ -r "$config_dir/app_key" ]; then
    APP_KEY="$(cat "$config_dir/app_key")"
    export APP_KEY
fi

mkdir -p storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
chown www-data:www-data storage/app storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache

setup_mode="$(runuser -u www-data -- php docker/setup-mode.php)"
if [ "${1:-}" = apache2-foreground ]; then
    if [ "$setup_mode" != off ]; then
        runuser -u www-data -- php artisan migrate --force --no-interaction
    fi
    if [ "$setup_mode" = demo ]; then
        runuser -u www-data -- php artisan db:seed --class=DemoInstallationSeeder --force --no-interaction
    fi
fi

if [ "${1:-}" = php ]; then
    exec runuser -u www-data -- "$@"
fi

exec docker-php-entrypoint "$@"
