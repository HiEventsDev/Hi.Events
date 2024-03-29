#!/bin/bash

RED='\033[0;31m'
GREEN='\033[0;32m'
BG_BLACK='\033[40m'
NC='\033[0m' # No Color

echo -e "${GREEN}${BG_BLACK}Installing Hi.Events...${NC}"

docker-compose -f docker-compose.dev.yml up -d

if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to start services with docker-compose.${NC}"
    exit 1
fi

echo -e "${GREEN}Running composer install in the backend service...${NC}"
docker-compose -f docker-compose.dev.yml exec -T backend composer install \
                                        --ignore-platform-reqs \
                                        --no-interaction \
                                        --no-dev \
                                        --optimize-autoloader \
                                        --prefer-dist

if [ $? -ne 0 ]; then
    echo -e "${RED}Composer install failed within the backend service.${NC}"
    exit 1
fi

echo -e "${GREEN}Hi.Events is now running at:${NC} http://localhost:5678"

case "$(uname -s)" in
    Darwin) open http://localhost:5678 ;;
    Linux) xdg-open http://localhost:5678 ;;
esac
