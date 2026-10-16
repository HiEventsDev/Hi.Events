---
paths:
  - "backend/app/**/*Spam*.php"
  - "backend/app/**/SpamEvents/**"
  - "backend/app/**/*Suspicious*.php"
  - "backend/app/**/*Turnstile*.php"
  - "backend/app/Validators/Rules/PersonNameRule.php"
  - "frontend/src/components/common/Turnstile/**"
---

# Abuse & spam

- Any new public order, waitlist or contact path must respect event status: public orders on non-LIVE events only for the event's own account and never on PENDING_MANUAL_REVIEW; waitlist joins and organizer contact require LIVE.
- New abuse signals go in `SuspiciousActivityDetector`.
