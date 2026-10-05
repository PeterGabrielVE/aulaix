#!/bin/sh
set -e

# storage/ and bootstrap/cache/ are bind-mounted from the host, so their
# ownership drifts to whatever last wrote them (often root, from `docker
# compose exec app ...` commands) — but php-fpm's worker processes run as
# www-data and need write access (compiled views, logs, cached config).
# Re-assert ownership on every start so the app doesn't silently lose the
# ability to log errors or compile views.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
