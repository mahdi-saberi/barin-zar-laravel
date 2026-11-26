#!/bin/bash
set -u

docker-php-entrypoint "$@" &
web_pid=$!
runuser -u www-data -- php artisan schedule:work --no-interaction &
scheduler_pid=$!

stop_children() {
    kill "$web_pid" "$scheduler_pid" 2>/dev/null || true
    wait "$web_pid" "$scheduler_pid" 2>/dev/null || true
}
trap stop_children EXIT
trap 'exit 0' INT TERM

wait -n "$web_pid" "$scheduler_pid"
exit 1
