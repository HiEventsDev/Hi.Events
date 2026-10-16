---
paths:
  - "backend/app/Mail/**"
  - "backend/app/**/Email/**"
  - "backend/app/**/EmailDelivery/**"
  - "backend/app/**/EmailTemplate/**"
  - "backend/app/**/*Mail*.php"
  - "backend/app/**/*Email*.php"
  - "backend/resources/views/emails/**"
---

# Mail & email delivery

- Mail queued before a deploy must still unserialize after it: a property added to a mailable is non-readonly with a `null` default.
- A new rich-text email token that carries HTML must be purified at store time and added to `PURIFIED_HTML_PATHS` in `EmailContextHtmlEscaper`; every other token is escaped.
- Email delivery recording (`RecordEmailDeliveryListener`) must never throw: a failed queued mail job is retried and re-sends the email. `email_deliveries` rows hold no recipient or account data.
- Link `email_deliveries`, `outgoing_messages` and `account_lifecycle_nudges` through `provider_message_id`, never with foreign keys.
- Don't add Symfony `TagHeader`s for tracking: Postmark rejects more than one tag per email.
- Pick a delivery webhook receiver by the `{provider}` route segment (`EmailDeliveryWebhookReceiverResolver`), never by sniffing the payload. The webhook route is unthrottled on purpose: unauthenticated calls never touch the DB, and throttling Postmark's few IPs during a broadcast would drop events.
