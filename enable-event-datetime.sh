#!/bin/bash

# Script to show DATE & TIME field in Checkout Summary page
# This will uncomment the Event Date display in the EventDetails component

set -e

FRONTEND_FILE="frontend/src/components/routes/product-widget/OrderSummaryAndProducts/index.tsx"
BACKUP_FILE="frontend/src/components/routes/product-widget/OrderSummaryAndProducts/index.tsx.backup"

echo "================================================"
echo "Enabling Event Date & Time in Checkout Summary"
echo "================================================"

# Check if file exists
if [ ! -f "$FRONTEND_FILE" ]; then
    echo "❌ Error: File not found: $FRONTEND_FILE"
    exit 1
fi

# Check if already enabled
if ! grep -q "// DISABLED:" "$FRONTEND_FILE"; then
    echo "⚠️  Event Date & Time is already enabled!"
    exit 0
fi

echo "🔧 Restoring frontend code..."

# Uncomment the DetailItem that shows Event Date
sed -i.tmp 's/^\/\/ DISABLED: //' "$FRONTEND_FILE"

# Remove temporary file
rm -f "${FRONTEND_FILE}.tmp"

echo "✅ Event Date & Time field has been enabled!"
echo ""
echo "📌 Next steps:"
echo "   1. Rebuild the frontend:"
echo "      cd frontend && npm run build"
echo "   2. Restart Docker containers:"
echo "      cd docker/all-in-one && docker compose restart"
echo ""
echo "💡 To disable again, run: ./disable-event-datetime.sh"
echo "================================================"
