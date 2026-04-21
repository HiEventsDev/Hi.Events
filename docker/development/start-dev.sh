#!/bin/bash

COMPOSE_CMD="docker compose -f docker-compose.dev.yml"
CERTS_FLAG="$1"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m' # No Color
CERTS_DIR="./certs"

print_banner() {
    echo ""
    echo -e "${CYAN}${BOLD}  ╔═══════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}${BOLD}  ║                                           ║${NC}"
    echo -e "${CYAN}${BOLD}  ║          ${MAGENTA}Hi.Events Dev Launcher${CYAN}           ║${NC}"
    echo -e "${CYAN}${BOLD}  ║                                           ║${NC}"
    echo -e "${CYAN}${BOLD}  ╚═══════════════════════════════════════════╝${NC}"
    echo ""
}

step() {
    echo -e "${BLUE}${BOLD}▶${NC} ${BOLD}$1${NC}"
}

info() {
    echo -e "  ${DIM}$1${NC}"
}

ok() {
    echo -e "  ${GREEN}✓${NC} $1"
}

warn() {
    echo -e "  ${YELLOW}⚠${NC} $1"
}

fail() {
    echo -e "  ${RED}✗${NC} $1"
}

# Prompt yes/no. $1 = question, $2 = default ("y" or "n")
ask_yes_no() {
    local prompt="$1"
    local default="$2"
    local hint
    if [ "$default" = "y" ]; then
        hint="${BOLD}Y${NC}/n"
    else
        hint="y/${BOLD}N${NC}"
    fi
    while true; do
        echo -ne "${YELLOW}?${NC} ${BOLD}$prompt${NC} [$hint] "
        read -r reply
        reply="${reply:-$default}"
        case "$reply" in
            [Yy]*) return 0 ;;
            [Nn]*) return 1 ;;
            *) echo -e "  ${DIM}Please answer y or n.${NC}" ;;
        esac
    done
}

print_banner

mkdir -p "$CERTS_DIR"

generate_unsigned_certs() {
    if [ ! -f "$CERTS_DIR/localhost.crt" ] || [ ! -f "$CERTS_DIR/localhost.key" ]; then
        step "Generating unsigned SSL certificates"
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout "$CERTS_DIR/localhost.key" -out "$CERTS_DIR/localhost.crt" -subj "/CN=localhost" > /dev/null 2>&1
        ok "Certificates generated"
    else
        ok "SSL certificates already exist"
    fi
}

generate_signed_certs() {
    if [ ! -f "$CERTS_DIR/localhost.crt" ] || [ ! -f "$CERTS_DIR/localhost.key" ]; then
        if ! command -v mkcert &> /dev/null; then
            fail "mkcert is not installed."
            info "Install via https://github.com/FiloSottile/mkcert#installation"
            info "Or use unsigned certs: '--certs=unsigned' (or omit --certs)"
            exit 1
        else
            step "Generating signed SSL certificates with mkcert"
            mkcert -key-file "$CERTS_DIR/localhost.key" -cert-file "$CERTS_DIR/localhost.crt" localhost 127.0.0.1 ::1 > /dev/null 2>&1
            ok "Certificates generated"
        fi
    else
        ok "SSL certificates already exist"
    fi
}

case "$CERTS_FLAG" in
    --certs=signed)
        generate_signed_certs
        ;;
    *)
        generate_unsigned_certs
        ;;
esac

echo ""
step "Setup options"

WIPE_DB=false
if ask_yes_no "Wipe the database and start fresh?" "n"; then
    WIPE_DB=true
    warn "Database will be wiped on startup"
else
    info "Keeping existing database"
fi

REINSTALL_DEPS=true
if ask_yes_no "Reinstall frontend dependencies (yarn install)?" "y"; then
    REINSTALL_DEPS=true
    info "Frontend image will be rebuilt with fresh deps"
else
    REINSTALL_DEPS=false
    info "Skipping frontend dependency reinstall"
fi

echo ""

if [ "$WIPE_DB" = true ]; then
    step "Tearing down existing containers and volumes"
    $COMPOSE_CMD down -v > /dev/null 2>&1
    ok "Containers and volumes removed"
elif [ "$REINSTALL_DEPS" = true ]; then
    step "Removing frontend container to refresh node_modules"
    $COMPOSE_CMD rm -sfv frontend > /dev/null 2>&1
    ok "Frontend container removed"
fi

if [ "$REINSTALL_DEPS" = true ]; then
    step "Rebuilding frontend image (running yarn install)"
    if ! $COMPOSE_CMD build frontend; then
        fail "Frontend image build failed"
        exit 1
    fi
    ok "Frontend image rebuilt"
fi

step "Starting services"
if ! $COMPOSE_CMD up -d; then
    fail "Failed to start services with docker compose."
    exit 1
fi
ok "Services started"

step "Running composer install in the backend service"
if ! $COMPOSE_CMD exec -T backend composer install \
                                        --ignore-platform-reqs \
                                        --no-interaction \
                                        --optimize-autoloader \
                                        --prefer-dist; then
    fail "Composer install failed within the backend service."
    exit 1
fi
ok "Composer dependencies installed"

step "Waiting for the database to be ready"
while ! $COMPOSE_CMD logs pgsql 2>/dev/null | grep "ready to accept connections" > /dev/null; do
    echo -n '.'
    sleep 1
done
echo ""
ok "Database is ready"

if [ ! -f ./../../backend/.env ]; then
    $COMPOSE_CMD exec backend cp .env.example .env
fi

if [ ! -f ./../../frontend/.env ]; then
    $COMPOSE_CMD exec frontend cp .env.example .env
fi

step "Running migrations and setup"
$COMPOSE_CMD exec backend php artisan key:generate
$COMPOSE_CMD exec backend php artisan migrate
$COMPOSE_CMD exec backend chmod -R 775 /var/www/html/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer
$COMPOSE_CMD exec backend php artisan storage:link

if [ $? -ne 0 ]; then
    fail "Migrations failed."
    exit 1
fi
ok "Migrations complete"

echo ""
step "Background workers"

if ask_yes_no "Start the queue worker?" "y"; then
    $COMPOSE_CMD exec -d backend php artisan queue:work --queue=default,webhook-queue --sleep=3 --tries=3 --timeout=60
    ok "Queue worker started (detached)"
else
    info "Skipped queue worker — start it later with:"
    info "$COMPOSE_CMD exec backend php artisan queue:work"
fi

if ask_yes_no "Start the scheduler?" "y"; then
    $COMPOSE_CMD exec -d backend php artisan schedule:work
    ok "Scheduler started (detached)"
else
    info "Skipped scheduler — start it later with:"
    info "$COMPOSE_CMD exec backend php artisan schedule:work"
fi

echo ""
echo -e "${GREEN}${BOLD}  🎉 Hi.Events is now running at:${NC} ${CYAN}${BOLD}https://localhost:8443${NC}"
echo ""

case "$(uname -s)" in
    Darwin) open https://localhost:8443/auth/register ;;
    Linux) xdg-open https://localhost:8443/auth/register ;;
    MINGW*|MSYS*|CYGWIN*) cmd //c start https://localhost:8443/auth/register ;;
esac
