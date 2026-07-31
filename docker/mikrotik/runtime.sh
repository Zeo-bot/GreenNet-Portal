#!/bin/sh
set -eu

if [ -z "${MIKROTIK_HOST:-}" ] || [ "${MIKROTIK_HOST}" = "auto" ]; then
    MIKROTIK_HOST="$(ip route show default 2>/dev/null | awk 'NR == 1 { print $3 }')"
    if [ -z "$MIKROTIK_HOST" ]; then
        echo "GreenNet startup error: RouterOS Apps gateway could not be resolved." >&2
        exit 1
    fi
    export MIKROTIK_HOST
fi

: "${MIKROTIK_TIMEOUT:=3}"
export MIKROTIK_TIMEOUT

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
