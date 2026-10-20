---
paths:
  - "backend/ee/Seating/**"
  - "frontend/src/ee/seating/**"
  - "backend/tests/Fixtures/seating/**"
  - "e2e/**/seating/**"
---

# Reserved seating

- A product is seated iff a price **band** links to it; never call a band a tier.
- Take seat claims under `SeatingEventLockService::lock`, order lock first.
- Rules that exist in both PHP and TS are pinned to `backend/tests/Fixtures/seating/`; change the fixture first.
