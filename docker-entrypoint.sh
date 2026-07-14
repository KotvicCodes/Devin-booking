#!/bin/sh
set -e

# Ensure the web/worker user owns the writable paths (needed when volumes are
# mounted with root ownership) without granting world access. php-fpm, nginx and
# the queue/scheduler all run as www-data, so owner+group rwX is sufficient.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R u=rwX,g=rwX,o= storage bootstrap/cache 2>/dev/null || true

# Clear stale provider cache that may reference removed packages
rm -f bootstrap/cache/packages.php

# If command is passed (e.g., queue:work), run it directly
# Otherwise run supervisord
if [ $# -gt 0 ]; then
    exec "$@"
else
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
