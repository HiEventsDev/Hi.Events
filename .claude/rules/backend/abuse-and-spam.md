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
- ARCHIVED events are spam-checked like LIVE ones (`EventSpamCheckService::CHECKED_EVENT_STATUSES`): on entering the status and on content edits. Approving a flagged event restores `previous_event_status`. Mail is not withheld while the first check runs; that window is accepted.
- Any user text that reaches a buyer's email belongs in `EventSpamCheckContentService`, and editing it dispatches a re-check through `EventSpamCheckDispatchService`. Custom email templates go in as raw Liquid source (hashed) plus a sample render (not hashed: the preview context is random); never strip Liquid, string literals print.
- Spam-review notices go to the account email, never `organizers.email` (unverified, free text).
