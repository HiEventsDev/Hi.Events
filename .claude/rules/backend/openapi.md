---
paths:
  - "backend/resources/api-reference/**"
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
- `backend/resources/api-reference/openapi.json` is the generated in-app API reference (Settings → API keys → API reference): Scramble output minus session-only operations and `/auth/*`, without `servers` and `info.version` (added when served). Regenerate it with `php artisan api-reference:export`; `IntegrationApiReferenceTest` fails when it drifts. Never edit it by hand.
- The docs intro in `config/scramble.php` promises that endpoints API keys can call stay backward compatible (see "Public API compatibility" in AGENTS.md) and lists the error codes. Update its error table when adding an `error_code`. Session-only operations are marked "Not available to API keys" automatically by `ScrambleServiceProvider`.
- Admin routes and the licensing endpoints (`Enterprise\Licensing` actions) are excluded from all generated docs in `ScrambleServiceProvider`.
