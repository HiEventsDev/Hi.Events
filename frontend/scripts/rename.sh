#!/bin/bash

replace_content() {
    local file="$1"
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS version
        sed -i '' -e 's/ticket/product/g; s/Ticket/Product/g; s/TICKET/PRODUCT/g' "$file"
    else
        # Linux version
        sed -i 's/ticket/product/g; s/Ticket/Product/g; s/TICKET/PRODUCT/g' "$file"
    fi
}

rename_item() {
    local item="$1"
    local dir=$(dirname "$item")
    local base=$(basename "$item")
    local newbase=$(echo "$base" | sed 's/ticket/product/g; s/Ticket/Product/g; s/TICKET/PRODUCT/g')

    if [ "$base" != "$newbase" ]; then
        mv "$item" "$dir/$newbase"
        echo "Renamed: $item -> $dir/$newbase"
    fi
}

process_directory() {
    local dir="$1"

    # First, rename directories (bottom-up to avoid path issues)
    find "$dir" -depth -type d | while read -r item; do
        if echo "$item" | grep -qi "ticket"; then
            rename_item "$item"
        fi
    done

    # Then, find all files in the directory and its subdirectories
    find "$dir" -type f | while read -r file; do
        # Check if the file name contains "ticket" (case insensitive)
        if echo "$file" | grep -qi "ticket"; then
            rename_item "$file"
        fi

        # Check if the file content contains "ticket" (case insensitive)
        if grep -qi "ticket" "$file"; then
            replace_content "$file"
            echo "Modified content: $file"
        fi
    done
}

if [ $# -eq 0 ]; then
    echo "Usage: $0 <directory>"
    exit 1
fi

if [ ! -d "$1" ]; then
    echo "Error: $1 is not a directory"
    exit 1
fi

process_directory "$1"

# Remove any leftover -e files (backup files created by sed on some systems)
find "$1" -name "*-e" -type f -delete

echo "Renaming and replacement complete."
echo "Removed any leftover -e backup files."
