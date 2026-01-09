#!/bin/bash

# Script to hide DATE & TIME field in Checkout Summary page
# This will comment out the Event Date display in the EventDetails component

set -e

FRONTEND_FILE="frontend/src/components/routes/product-widget/OrderSummaryAndProducts/index.tsx"
BACKUP_FILE="frontend/src/components/routes/product-widget/OrderSummaryAndProducts/index.tsx.backup"

echo "================================================"
echo "Disabling Event Date & Time in Checkout Summary"
echo "================================================"

# Check if file exists
if [ ! -f "$FRONTEND_FILE" ]; then
    echo "❌ Error: File not found: $FRONTEND_FILE"
    exit 1
fi

# Create backup if it doesn't exist
if [ ! -f "$BACKUP_FILE" ]; then
    echo "📦 Creating backup..."
    cp "$FRONTEND_FILE" "$BACKUP_FILE"
    echo "✅ Backup created: $BACKUP_FILE"
fi

# Check if already disabled
if grep -q "// DISABLED: <DetailItem" "$FRONTEND_FILE"; then
    echo "⚠️  Event Date & Time is already disabled!"
    exit 0
fi

echo "🔧 Modifying frontend code..."

# Comment out the DetailItem that shows Event Date (lines 303-307)
# We need to comment the entire block including the closing />
sed -i.tmp \
    -e '303s/^/\/\/ DISABLED: /' \
    -e '304s/^/\/\/ DISABLED: /' \
    -e '305s/^/\/\/ DISABLED: /' \
    -e '306s/^/\/\/ DISABLED: /' \
    -e '307s/^/\/\/ DISABLED: /' \
    "$FRONTEND_FILE"

# Remove temporary file
rm -f "${FRONTEND_FILE}.tmp"

echo "✅ Event Date & Time field has been disabled!"
echo ""
echo "📌 Next steps:"
echo "   1. Rebuild the frontend:"
echo "      cd frontend && npm run build"
echo "   2. Restart Docker containers:"
echo "      cd docker/all-in-one && docker compose restart"
echo ""
echo "💡 To re-enable, run: ./enable-event-datetime.sh"
echo "================================================"
