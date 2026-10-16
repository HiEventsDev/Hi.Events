---
paths:
  - "backend/app/OpenApi/**"
  - "backend/app/Providers/ScrambleServiceProvider.php"
  - "backend/config/scramble.php"
  - "backend/app/Http/Request/**"
  - "backend/app/Resources/**"
  - "backend/routes/api.php"
---

# OpenAPI docs (Scramble)

- After adding or changing endpoints run `php artisan scramble:analyze`; it must stay clean.
- Status/type fields in the Order/Event/Attendee/Product resources carry `/** @var 'A'|'B' */` literal-union annotations that render as enums; update them when adding enum cases.
- `CreateOrderRequest`/`CompleteOrderRequest` return their real rules only when a route is bound and a static documentation shape otherwise; keep the two in sync when changing checkout validation.
- Adding a `DomainEventType` case means updating the webhook payload table in `config/scramble.php`.
