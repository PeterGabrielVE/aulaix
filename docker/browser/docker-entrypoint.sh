#!/bin/sh
set -e

# vendor/ is shared with the app container; node_modules/ is this
# container's own (Linux-native) volume, filled on first run.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -x node_modules/.bin/playwright ]; then
    npm ci --no-audit --no-fund
fi

# The pages under test load the built assets, never a dev server.
npm run build --silent

exec "$@"
