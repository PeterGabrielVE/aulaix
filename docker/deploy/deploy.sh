#!/usr/bin/env bash
# Runs ON THE SERVER (uploaded by .github/workflows/deploy.yml).
#
# Usage: IMAGE_PREFIX=ghcr.io/owner/repo deploy.sh <image-tag>
#
# Expects $APP_DIR to hold docker-compose.prod.yml and the production .env.
# Pulls the new images, runs migrations, swaps the containers and checks
# /up; if the health check fails it puts the previous tag back.
set -euo pipefail

NEW_TAG="${1:?usage: deploy.sh <image-tag>}"
: "${IMAGE_PREFIX:?IMAGE_PREFIX is required}"
APP_DIR="${APP_DIR:-/opt/aulaix}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:${HTTP_PORT:-8080}/up}"

cd "$APP_DIR"
export IMAGE_PREFIX

PREVIOUS_TAG="$(cat .deployed_tag 2>/dev/null || true)"

compose() {
    IMAGE_TAG="$1" docker compose -f docker-compose.prod.yml "${@:2}"
}

echo "==> Deploying $NEW_TAG (previous: ${PREVIOUS_TAG:-none})"
compose "$NEW_TAG" pull app web ai-service

# Migrations run before the new code takes traffic, so they must be
# backwards compatible with the version still running (expand/contract).
echo "==> Running migrations"
compose "$NEW_TAG" run --rm app php artisan migrate --force

echo "==> Starting containers"
compose "$NEW_TAG" up -d --remove-orphans

echo "==> Health check: $HEALTH_URL"
for _ in $(seq 1 30); do
    if curl -fsS -o /dev/null "$HEALTH_URL"; then
        echo "$NEW_TAG" > .deployed_tag
        docker image prune -f > /dev/null
        echo "==> $NEW_TAG is live"
        exit 0
    fi
    sleep 2
done

echo "!! Health check failed for $NEW_TAG" >&2
if [ -n "$PREVIOUS_TAG" ]; then
    echo "==> Rolling back containers to $PREVIOUS_TAG (migrations are NOT rolled back)" >&2
    compose "$PREVIOUS_TAG" up -d --remove-orphans
fi
exit 1
