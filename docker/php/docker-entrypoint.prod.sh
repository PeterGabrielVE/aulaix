#!/bin/sh
set -e

# Config, routes, views and events are cached at container start, not at
# build time: config:cache freezes env() values, and the real ones (.env on
# the server) only exist at runtime. routes/web.php reads config('app.domain')
# when registering the tenant routes, so the route cache must be built with
# the production APP_DOMAIN too. One-off commands (`run app php artisan
# migrate`) skip this.
if [ "$1" = "php-fpm" ]; then
    php artisan optimize
fi

exec "$@"
