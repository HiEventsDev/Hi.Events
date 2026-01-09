# Event Date & Time Toggle Scripts

## Overview

This directory contains two shell scripts to toggle the visibility of the **DATE & TIME** field on the Checkout Summary page.

## Scripts

### 1. `disable-event-datetime.sh`
Hides the Event Date & Time field from the Checkout Summary page.

### 2. `enable-event-datetime.sh`
Shows the Event Date & Time field on the Checkout Summary page.

## Usage

### To Hide Event Date & Time:

```bash
./disable-event-datetime.sh
```

### To Show Event Date & Time:

```bash
./enable-event-datetime.sh
```

## Important Notes

1. **Backup**: The first time you run `disable-event-datetime.sh`, a backup file will be created at:
   ```
   frontend/src/components/routes/product-widget/OrderSummaryAndProducts/index.tsx.backup
   ```

2. **Rebuild Required**: After running either script, you need to rebuild the frontend and restart Docker:
   ```bash
   # Rebuild frontend
   cd frontend && npm run build

   # Restart Docker containers
   cd docker/all-in-one && docker compose restart
   ```

3. **Affected Page**: These scripts modify the Checkout Summary page shown after a customer completes a ticket purchase.
   - Example URL: `https://conference.devz.nida.ac.th/checkout/17/o_XXXXX/summary`

## What Gets Modified

The scripts modify the `EventDetails` component in:
```
frontend/src/components/routes/product-widget/OrderSummaryAndProducts/index.tsx
```

Specifically, they comment/uncomment the `DetailItem` component that displays:
- **Label**: "Event Date"
- **Icon**: Calendar icon (IconCalendarEvent)
- **Value**: Event date range

## Example

**Before (Enabled)**:
```
Event Details
┌─────────────────────┬─────────────────────┐
│ 📅 Event Date       │ 📍 Location         │
│ Jan 15, 2026        │ Bangkok, Thailand   │
├─────────────────────┼─────────────────────┤
│ 🕐 Timezone         │ 🏢 Organizer        │
│ Asia/Bangkok        │ Event Organizer     │
└─────────────────────┴─────────────────────┘
```

**After (Disabled)**:
```
Event Details
┌─────────────────────┬─────────────────────┐
│ 📍 Location         │ 🕐 Timezone         │
│ Bangkok, Thailand   │ Asia/Bangkok        │
├─────────────────────┼─────────────────────┤
│ 🏢 Organizer        │                     │
│ Event Organizer     │                     │
└─────────────────────┴─────────────────────┘
```

## Troubleshooting

### Script won't run (Permission Denied)
```bash
chmod +x disable-event-datetime.sh enable-event-datetime.sh
```

### Changes not appearing
Make sure to rebuild and restart:
```bash
cd frontend && npm run build
cd ../docker/all-in-one && docker compose restart
```

### Restore original file
If something goes wrong, you can restore from backup:
```bash
cp frontend/src/components/routes/product-widget/OrderSummaryAndProducts/index.tsx.backup \
   frontend/src/components/routes/product-widget/OrderSummaryAndProducts/index.tsx
```

## Technical Details

- **Method**: Scripts add/remove `// DISABLED:` comments to code lines
- **Lines Modified**: Lines 303-307 in the index.tsx file
- **Safe**: Original code is preserved in backup file
- **Reversible**: Can toggle between enabled/disabled states anytime

## Support

If you encounter any issues, please check:
1. You're running scripts from the project root directory
2. The frontend file exists and hasn't been moved
3. You have write permissions to modify files
4. Docker containers are running

---

**Created**: 2026-01-09
**Location**: `/home/user/Hi.Events/`
