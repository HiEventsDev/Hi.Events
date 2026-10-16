---
paths:
  - "backend/app/**/LifecycleEmail*/**"
  - "backend/app/**/Lifecycle/**"
  - "backend/app/**/*Lifecycle*.php"
  - "backend/tests/**/*Lifecycle*"
  - "frontend/src/**/LifecycleEmails/**"
  - "frontend/src/**/*ifecycle*"
---

# Lifecycle emails

- Send only through `MAIL_LIFECYCLE_MAILER`, test sends included; never fall back to the transactional mailer. Everything lifecycle exists only in SaaS mode (`LifecycleEmailAvailability`).
- Every candidate query must: exclude `users.lifecycle_emails_unsubscribed_at`; skip accounts nudged in the last 3 days (`quietPeriodStart()`); filter in SQL before `LIMIT`, never in PHP after it; and anchor its trigger on a timestamp old data can't move (never `updated_at` unless also bounded by a creation date after `enabled_at`). Marketing-flavoured nudges also require `users.marketing_opted_in_at`.
- A template's `enabled_at` is the cold-start cutoff; never backdate it.
- Unsubscribe is a button on `/emails/unsubscribe/:token`, never unsubscribe-on-GET (link scanners prefetch).
- `account_lifecycle_nudges` is claim-then-send: only transient failures (network, HTTP 5xx/429) release the claim and stop the sweep; any other rejection keeps the claim so one bad address never stalls it. Nudges are sent synchronously, not via `BaseMail`, so failures can be classified.
- Stripe nudges filter by signup timezone (`StripeConnectTimezones`); don't use `organizer_stripe_platforms.stripe_account_details->country`, because unfinished Express accounts report the platform default (`IE`).
