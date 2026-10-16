---
paths:
  - "backend/ee/BoxOffice/**"
  - "frontend/src/ee/box-office/**"
  - "e2e/**/box-office/**"
---

# Box office

- Door endpoints are public, authenticated by a PIN session token: never expose attendee PII, and never cancel another live sale's reader prompt.
