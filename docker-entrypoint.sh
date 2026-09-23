#!/bin/sh
set -e

# Ensure required framework runtime directories exist
mkdir -p \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs \
    /app/bootstrap/cache

exec docker-php-entrypoint "$@"
