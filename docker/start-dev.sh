#!/bin/bash

COMPOSE_CMD="docker-compose -f docker-compose.dev.yml"

RED='\033[0;31m'
GREEN='\033[0;32m'
BG_BLACK='\033[40m'
NC='\033[0m' # No Color

echo -e "${GREEN}${BG_BLACK}Installing Hi.Events...${NC}"

$COMPOSE_CMD up -d

if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to start services with docker-compose.${NC}"
    exit 1
fi

echo -e "${GREEN}Running composer install in the backend service...${NC}"
$COMPOSE_CMD exec -T backend composer install \
                                        --ignore-platform-reqs \
                                        --no-interaction \
                                        --no-dev \
                                        --optimize-autoloader \
                                        --prefer-dist

if [ $? -ne 0 ]; then
    echo -e "${RED}Composer install failed within the backend service.${NC}"
    exit 1
fi

# It's fine to run key:generate here for development environments
# but DO NOT regenerate the get every time in production environments
$COMPOSE_CMD exec backend cp .env.example .env
$COMPOSE_CMD exec backend php artisan key:generate

$COMPOSE_CMD exec backend php artisan migrate

echo -e "${GREEN}Hi.Events is now running at:${NC} http://localhost:5678"

case "$(uname -s)" in
    Darwin) open http://localhost:5678 ;;
    Linux) xdg-open http://localhost:5678 ;;
    MINGW*|MSYS*|CYGWIN*) cmd //c start http://localhost:5678 ;; # Added for Windows support in Git Bash or similar
esac
