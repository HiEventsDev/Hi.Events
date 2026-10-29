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
- Door printing goes through the public check-in list endpoints and only works on lists with `badge_printing_enabled`; the public layout (`BadgeDesignLayoutResource`) leaves out question and ticket ids. Kiosk lists refuse the attendee list and detail endpoints so attendees can't browse each other.
- Backs print one of three ways (`getDesignBackPrinting`): duplex pages mirror each row for a long-edge flip, fold-over units (`fold_over`) put the back beside a portrait badge or below a landscape one turned 180°, or no back. Door devices choose their own back mode in `useBadgePrinter` settings, defaulting to the design's fold-over or front only.
- Badges render at physical size (`mm` plus container query units) and text shrinks to fit with `FitText`; `FitBody` scales a template's main area down when the lines are taller than the space left. Print through the browser (`window.print()` with an `@page` size); E2E specs stub `window.print` with `stubBrowserPrinting`.
- A door badge includes the answers bound to the design even when the list hides question answers from unauthenticated staff: the organizer chose to print them.
- Door printing queues jobs in `useBadgePrinter`; never replace the job being printed.
