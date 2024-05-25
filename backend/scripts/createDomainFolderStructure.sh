#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

if [ $# -eq 0 ]; then
    echo "No domain name provided. Usage: $0 <domain_name>"
    exit 1
fi

DOMAIN_NAME=$1

BASE_PATH="$SCRIPT_DIR/../app/Domains/$DOMAIN_NAME"

DIRECTORIES=(
    "Services/Handlers"
    "Http/Requests"
    "Http/DataTransferObjects"
    "Http/Middleware"
    "Http/Actions"
    "Repositories/Contracts"
    "Repositories/Eloquent"
    "Models/Eloquent"
    "Mail"
    "Resources"
    "DomainObjects"
    "Exceptions"
)

for dir in "${DIRECTORIES[@]}"; do
    mkdir -p "$BASE_PATH/$dir"
done

echo "Folder structure for '$DOMAIN_NAME' created at $BASE_PATH"
