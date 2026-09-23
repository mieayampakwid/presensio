#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="compose.production.yaml"
APP_SERVICE="app"

# Read KEY=VALUE from .env without sourcing it (handles double quotes).
env_value() {
    local key="$1" default="$2" value
    value=$(grep -E "^${key}=" .env 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"' || true)
    echo "${value:-$default}"
}

compose() {
    docker compose -f "$COMPOSE_FILE" "$@"
}

info()  { printf '\033[0;32m[deploy]\033[0m %s\n' "$*"; }
warn()  { printf '\033[0;33m[warn]\033[0m %s\n' "$*"; }
error() { printf '\033[0;31m[error]\033[0m %s\n' "$*" >&2; }

preflight() {
    command -v docker >/dev/null 2>&1 || { error "docker is not installed."; exit 1; }
    docker compose version >/dev/null 2>&1 || { error "docker compose plugin is required."; exit 1; }
    [ -f .env ] || { error ".env not found. Copy .env.example, then set APP_KEY."; exit 1; }
    [ -n "$(env_value APP_KEY "")" ] || { error "APP_KEY is empty in .env. Generate one: docker compose -f compose.production.yaml run --rm app php artisan key:generate --show"; exit 1; }
}

wait_healthy() {
    local port="$1" i
    info "Waiting for app on http://localhost:${port} ..."
    for i in $(seq 1 60); do
        if curl -fsS "http://localhost:${port}/" >/dev/null 2>&1; then
            info "App is up."
            return 0
        fi
        sleep 2
    done
    error "App did not respond within 120s."
    compose ps
    compose logs app --tail 50
    exit 1
}

cmd_deploy() {
    preflight
    if [ -z "$(env_value WAHA_API_KEY "")" ]; then
        warn "WAHA_API_KEY is empty — WAHA regenerates its API key on every restart, so the app cannot authenticate to WAHA."
    fi
    info "Building image..."
    compose build
    info "Starting stack..."
    compose up -d --remove-orphans
    wait_healthy "$(env_value APP_PORT 80)"
    info "Running migrations..."
    compose exec -T "$APP_SERVICE" php artisan migrate --force
    info "Caching config, routes, and views..."
    compose exec -T "$APP_SERVICE" php artisan optimize
    info "Deploy complete."
    compose ps
    printf '  App:            http://localhost:%s\n' "$(env_value APP_PORT 80)"
    printf '  WAHA dashboard: http://localhost:%s/dashboard/\n' "$(env_value WAHA_PORT 3000)"
}

cmd_up()       { preflight; compose up -d --remove-orphans; }
cmd_down()     { compose down; }
cmd_build()    { compose build; }
cmd_restart()  {
    if [ $# -eq 0 ]; then
        compose restart app queue cron
    else
        compose restart "$@"
    fi
}
cmd_logs()     { compose logs -f --tail 100 "${1:-$APP_SERVICE}"; }
cmd_ps()       { compose ps; }
cmd_migrate()  { compose exec -T "$APP_SERVICE" php artisan migrate --force; }
cmd_optimize() { compose exec -T "$APP_SERVICE" php artisan optimize; }
cmd_backup()   {
    local db user dir file
    db=$(env_value DB_DATABASE presensio)
    user=$(env_value DB_USERNAME presensio)
    dir=backups
    mkdir -p "$dir"
    file="${dir}/presensio_$(date +%Y%m%d_%H%M%S).sql.gz"
    compose exec -T pgsql pg_dump -U "$user" "$db" | gzip > "$file"
    info "Backup written: $file ($(du -h "$file" | cut -f1))"
}

usage() {
    cat <<'EOF'
Usage: ./deploy.sh [command]

Commands:
  (no command)   Full deploy: preflight, build, up, health-wait, migrate --force, optimize, summary
  build          Build the production image
  up             Start the stack (runs preflight)
  down           Stop the stack (volumes are kept)
  restart [svc]  Restart app+queue+cron, or just the named service
  logs [svc]     Follow logs (default: app)
  ps             Show service status
  migrate        Run pending migrations (--force)
  optimize       Cache config, routes, events, views
  backup         Dump pgsql to backups/presensio_<timestamp>.sql.gz
  help           Show this help
EOF
}

case "${1:-deploy}" in
    deploy)   cmd_deploy ;;
    build)    cmd_build ;;
    up)       cmd_up ;;
    down)     cmd_down ;;
    restart)  shift; cmd_restart "$@" ;;
    logs)     shift; cmd_logs "$@" ;;
    ps)       cmd_ps ;;
    migrate)  cmd_migrate ;;
    optimize) cmd_optimize ;;
    backup)   cmd_backup ;;
    help|-h|--help) usage ;;
    *)        error "Unknown command: $1"; usage; exit 1 ;;
esac
