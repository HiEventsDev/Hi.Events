---
paths:
  - "backend/app/**/Badge/**"
  - "backend/app/**/*Badge*.php"
  - "backend/tests/**/Badges/**"
  - "frontend/src/components/common/NameBadge/**"
  - "frontend/src/components/routes/event/Badges/**"
  - "frontend/src/components/modals/PrintBadgesModal/**"
  - "frontend/src/components/layouts/CheckIn/**"
  - "frontend/src/hooks/useBadgePrinter.ts"
  - "frontend/src/hooks/useCheckInController.tsx"
  - "frontend/src/utilites/badge*.ts"
  - "e2e/tests/badges/**"
---

# Name badges

- `BadgeDataService` resolves every badge line (names, ticket type, question answers) on the server. The frontend only renders `Badge` payloads; never resolve answers in the browser. The designer preview uses `buildSampleBadge` sample data, not real attendees.
- The design lives in `event_settings.badge_design_settings`; `null` means "use defaults", which `BadgeDesignService` builds from the event (currency picks A4 or Letter, brand colour and font come from the ticket and homepage designs, company/job title/pronoun questions are bound by title).
- Print status is derived (`AttendeeDomainObject::getBadgePrintStatus`) from `badge_print_count` and `badge_printed_at`. Anything that changes what is printed (name, ticket, seat, an answer on the badge) clears `badge_printed_at` through `BadgeReprintService`; don't add jobs or sweepers that write it.
- Question and ticket ids inside the design are validated against the event on save and remapped by `BadgeDesignCloneService` when an event is duplicated.
- Badges are an opt-in event feature: `EventFeature::BADGES` in `event_settings.enabled_features` (Event Settings → Features), off by default. Off hides every badge control in the UI and switches off door printing (`CheckInListDomainObject::isBadgePrintingActive`); the staff badge endpoints stay available. Seating and box office deliberately stay out of this switch: they show whenever their licence and Cloud flag allow.
- Door printing goes through the public check-in list endpoints and only works on lists with `badge_printing_enabled`; the public layout (`BadgeDesignLayoutResource`) leaves out question and ticket ids. Kiosk lists refuse the attendee list and detail endpoints so attendees can't browse each other. The only search on a kiosk is the ticket lookup (`kiosk_lookup_enabled`, on by default): an exact, case-insensitive email and last name match, scoped like a scan, returning only `public_id`, first name and ticket title, with its own `kiosk-ticket-lookup` throttle. The kiosk then checks the ticket in through the normal scan path.
- Backs print one of three ways (`getDesignBackPrinting`): duplex pages mirror each row for a long-edge flip, fold-over units (`fold_over`) put the back beside a portrait badge or below a landscape one turned 180°, or no back. Door devices choose their own back mode in `useBadgePrinter` settings, defaulting to the design's fold-over or front only.
- Badges render at physical size (`mm` plus container query units) and text shrinks to fit with `FitText`; `FitBody` scales a template's main area down when the lines are taller than the space left. `FitText` fits every line from a render in one batch (reset all, read all, write all per step); fitting one element at a time forces a layout per line and froze a 1,000-badge print page for 20 s.
- Badges print through the browser (`window.print()` with an `@page` size). Door printing goes through `printBadgeSheets`, which swaps the address to `/` for the call, because iOS Safari prints the page URL in the margins and the check-in URL is a bearer link. E2E specs stub `window.print` with `stubBrowserPrinting`, which records the printed path and whether the call came from a click.
- iPad and iPhone (`printsOnlyFromATap`) block `print()` outside a tap and fire `afterprint` while the print sheet is still open. There `useBadgePrinter` prepares the badge first (attendee sheet open, kiosk check-in, setup modal open), prints synchronously from the tap and marks it printed at that moment; auto-print is off and the kiosk shows Print my badge and Print again. Never call `print()` after an `await` on that path. The badge-only print layout stays on while a badge is prepared, because iOS re-renders the live page when the printer or paper changes in its sheet.
- iOS Safari ignores `@page` size and margins, keeps its own (12.7 mm sides and top, about 21 mm bottom for the URL/date footer), and evaluates print media queries against the screen. So on those devices `BadgePrintSheets` zooms the pages by `getIosPrintScale` to fit inside those margins; without it badges on badge-sized paper are cropped.
- `blackInkOnly` (per device) prints accent, ribbon and text as pure black and white and images in greyscale. The most common badge printer (Brother QL-820NWB over AirPrint) prints black only, so colour ribbons dither to unreadable grey without it.
- Kiosk staff controls (Set up, scanner mode) appear only after pressing and holding the list name. The kiosk keeps the screen awake and starts touch devices on the camera unless the device already chose a scanner.
- Device setup instructions live only in `DeviceSetupGuide` (the staffed Badge printing modal and the kiosk's Set up modal), with a copyable Chrome command per platform. Facts it relies on, checked in Chromium source: `--kiosk-printing` confirms print preview automatically and prints to the destination last used in that Chrome profile with its last settings (not the OS default printer unless the `PrintPreviewUseSystemDefaultPrinter` policy is set), so staff print one test badge first; if that destination was Save as PDF, badges save silently to Downloads. Switches only apply when Chrome starts, so the guide has staff quit Chrome first. The `SilentPrintingEnabled` policy (Chrome 144+) is the managed-device equivalent. `--kiosk` is only full screen (⌘Q or Alt+F4 still exit); iPads lock to the page with Guided Access.
- Public check-in routes skip the shared `throttle:api` bucket and use their own per-IP allowance, because every door device at a venue shares one IP (`CheckInRateLimitTest`).
- A door badge includes the answers bound to the design even when the list hides question answers from unauthenticated staff: the organizer chose to print them.
- Door printing queues jobs in `useBadgePrinter`; never replace the job being printed.
- Check print CSS changes against an emulated Brother QL-820NWB with the `badge-printer-emulator` skill.
