---
paths:
  - "backend/app/**/Lineup/**"
  - "backend/app/**/*Lineup*.php"
  - "frontend/src/**/Lineup/**"
  - "frontend/src/**/*ineup*"
  - "frontend/src/utilites/spreadsheetReader.ts"
  - "e2e/**/lineup*"
  - "e2e/**/lineup/**"
---

# Lineup & schedule

- The run-sheet export (`LineupExport`) and the import share column names. Import header labels use the Lingui context "Lineup spreadsheet column", whose translations must equal the backend's `__()` header strings, or a localized export won't re-import.
- People and stages match by `nameKey` on the frontend and `ImportLineupHandler::key` on the backend; keep the two identical or the preview lies.
- The import preview mirrors every server validation limit so one bad cell never fails the whole import. Change both sides together.
- `ImportLineupHandler` writes through repositories in bulk; don't route it through the per-row upsert handlers (far too slow for 1,000 slots).
- Per-date lineups for recurring events are derived from slot times (`lineupForOccurrence`), never stored. When the Schedule section is hidden the public payload has no slots, so on recurring events only each entry carries `schedule_days` (dates only, computed on read) and `lineupForOccurrence` filters on those. Single-date events never get `schedule_days`: a hidden schedule must not reveal which day each act plays.
- Ticket-email lineup highlights (caps, headliner/featured fallback, preferred slots) are picked by `LineupHighlightSelector`, which the spam check reuses to label that content `ticket email …` and put it first in the prompt. Change the selection there, never in a copy.
