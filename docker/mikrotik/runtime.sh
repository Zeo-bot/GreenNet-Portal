#!/bin/sh
set -eu

php-fpm -F &
php_pid="$!"

(
    while true; do
        su-exec www-data php /var/www/bin/greennet jobs:run || true
        sleep "${AUTOMATION_INTERVAL_SECONDS:-300}"
    done
) &
scheduler_pid="$!"

nginx -g "daemon off;" &
nginx_pid="$!"

shutdown() {
    kill -TERM "$nginx_pid" "$scheduler_pid" "$php_pid" 2>/dev/null || true
    wait "$nginx_pid" "$scheduler_pid" "$php_pid" 2>/dev/null || true
}

trap shutdown INT TERM
wait "$nginx_pid"
status="$?"
shutdown
exit "$status"
