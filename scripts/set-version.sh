#!/bin/bash

set -e

if [ -z "$1" ]; then
    echo "Usage: $0 <version>"
    echo "Example: $0 1.7.0-beta"
    exit 1
fi

VERSION="$1"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

echo "$VERSION" > "$ROOT_DIR/VERSION"
echo "Updated VERSION file to $VERSION"

awk -v ver="$VERSION" '!done && /"version":/ { sub(/"version": ".*"/, "\"version\": \"" ver "\""); done=1 } 1' "$ROOT_DIR/backend/composer.json" > "$ROOT_DIR/backend/composer.json.tmp" && mv "$ROOT_DIR/backend/composer.json.tmp" "$ROOT_DIR/backend/composer.json"
echo "Updated backend/composer.json to $VERSION"

awk -v ver="$VERSION" '!done && /"version":/ { sub(/"version": ".*"/, "\"version\": \"" ver "\""); done=1 } 1' "$ROOT_DIR/frontend/package.json" > "$ROOT_DIR/frontend/package.json.tmp" && mv "$ROOT_DIR/frontend/package.json.tmp" "$ROOT_DIR/frontend/package.json"
echo "Updated frontend/package.json to $VERSION"

echo "All files updated to version $VERSION"
