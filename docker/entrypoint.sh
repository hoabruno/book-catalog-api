#!/bin/sh
# Container entrypoint: caches the configuration from the environment, then
# runs the given command. Only the "app" service sets RUN_MIGRATIONS=1, so
# migrations never run concurrently from the worker and the scheduler.
set -e

php artisan optimize --no-ansi -q

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force --no-ansi
fi

exec "$@"
