#!/bin/sh
# Entrypoint for the `test` service in compose.test.yaml.
#   (no args)          -> php artisan test
#   --filter=Foo ...   -> php artisan test --filter=Foo ...
#   any other command  -> run it as-is (e.g. `php artisan tinker`, `sh`)
set -e

if ! sha1sum -c -s /opt/composer.lock.sha1 2>/dev/null; then
    echo "composer.lock changed since the test image was built." >&2
    echo "Rebuild it: docker compose -f compose.test.yaml run --build --rm test" >&2
    exit 1
fi

# storage/ and bootstrap/cache/ are tmpfs mounts (the source is mounted read-only), so
# recreate the directories Laravel expects on every run.
mkdir -p storage/app/public storage/logs \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    bootstrap/cache

if [ $# -eq 0 ]; then
    set -- php artisan test
elif [ "${1#-}" != "$1" ]; then
    set -- php artisan test "$@"
fi

exec "$@"
