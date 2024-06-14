#!/bin/bash

COMPOSE_CMD="docker-compose -f docker-compose.dev.yml"
CERTS_FLAG="$1"

RED='\033[0;31m'
GREEN='\033[0;32m'
BG_BLACK='\033[40m'
NC='\033[0m' # No Color
CERTS_DIR="./certs"

echo -e "${GREEN}${BG_BLACK}Installing Hi.Events...${NC}"

mkdir -p "$CERTS_DIR"

generate_unsigned_certs() {
    if [ ! -f "$CERTS_DIR/localhost.crt" ] || [ ! -f "$CERTS_DIR/localhost.key" ]; then
        echo -e "${GREEN}Generating unsigned SSL certificates...${NC}"
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout "$CERTS_DIR/localhost.key" -out "$CERTS_DIR/localhost.crt" -subj "/CN=localhost"
    else
        echo -e "${GREEN}SSL certificates already exist, skipping generation...${NC}"
    fi
}

generate_signed_certs() {
    if [ ! -f "$CERTS_DIR/localhost.crt" ] || [ ! -f "$CERTS_DIR/localhost.key" ]; then
        if ! command -v mkcert &> /dev/null; then
            echo -e "${RED}mkcert is not installed.${NC}"
            echo "Please install mkcert by following the instructions at: https://github.com/FiloSottile/mkcert#installation"
            echo "Alternatively, you can generate unsigned certificates by using '--certs=unsigned' or omitting the --certs flag."
            exit 1
        else
            echo -e "${GREEN}Generating signed SSL certificates with mkcert...${NC}"
            mkcert -key-file "$CERTS_DIR/localhost.key" -cert-file "$CERTS_DIR/localhost.crt" localhost 127.0.0.1 ::1
        fi
    else
        echo -e "${GREEN}SSL certificates already exist, skipping generation...${NC}"
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

$COMPOSE_CMD up -d

if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to start services with docker-compose.${NC}"
    exit 1
fi

echo -e "${GREEN}Running composer install in the backend service...${NC}"

$COMPOSE_CMD exec -T backend composer install \
                                        --ignore-platform-reqs \
                                        --no-interaction \
                                        --optimize-autoloader \
                                        --prefer-dist

if [ $? -ne 0 ]; then
    echo -e "${RED}Composer install failed within the backend service.${NC}"
    exit 1
fi

echo -e "${GREEN}Waiting for the database to be ready...${NC}"
while ! $COMPOSE_CMD logs pgsql | grep "ready to accept connections" > /dev/null; do
  echo -n '.'
  sleep 1
done

echo -e "\n${GREEN}Database is ready. Proceeding with migrations...${NC}"

if [ ! -f ./../../backend/.env ]; then
    $COMPOSE_CMD exec backend cp .env.example .env
fi

if [ ! -f ./../../frontend/.env ]; then
    $COMPOSE_CMD exec frontend cp .env.example .env
fi

$COMPOSE_CMD exec backend php artisan key:generate
$COMPOSE_CMD exec backend php artisan migrate
$COMPOSE_CMD exec backend chmod -R 775 /app/vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer
$COMPOSE_CMD exec backend php artisan storage:link

if [ $? -ne 0 ]; then
    echo -e "${RED}Migrations failed.${NC}"
    exit 1
fi

echo -e "${GREEN}Hi.Events is now running at:${NC} https://localhost:8443"

case "$(uname -s)" in
    Darwin) open https://localhost:8443/auth/register ;;
    Linux) xdg-open https://localhost:8443/auth/register ;;
    MINGW*|MSYS*|CYGWIN*) cmd //c start https://localhost:8443/auth/register ;;
esac
