---
name: api-smoke-test
description: Exercise changed backend endpoints against the running dev stack with curl. Use after backend changes to catch wiring bugs unit tests miss (routes, middleware, validation, serialization).
---

# API smoke test

Base URL `https://localhost:8443/api` (self-signed certificate, so `curl -sk`). Routes are in `backend/routes/api.php`. Send `Accept: application/json`, or validation errors come back as a 302 instead of a 422.

## Get data and a token

From `docker/development`:

```bash
docker compose -f docker-compose.dev.yml exec backend php artisan dev:bootstrap
```

This creates a verified SUPERADMIN account, an organizer, LIVE single and recurring events, products (the paid one has a waitlist), a promo code and an affiliate, and prints their ids and a Bearer token.

Manual login (`token` field → `Authorization: Bearer <token>`):

```bash
curl -sk -X POST https://localhost:8443/api/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"email":"<email>","password":"<password>"}'
```

## Request gotchas

- Product upsert requires `product_type` (TICKET/GENERAL), `type` (FREE/PAID/DONATION/TIERED) and `product_category_id`.
- Promo create requires `applicable_product_ids: []` (empty = all products).
- Public order complete requires `email_confirmation` on the order and on each product entry.
- Publishing needs `accounts.account_verified_at`; `/admin/*` needs role `SUPERADMIN`. `dev:bootstrap` handles both.
- Box office door API (`/api/public/box-offices/{short_id}`): `POST …/sessions` with `operator_name` and a `pin` (from `POST /api/events/{id}/box-offices/{box_office_id}/reset-pin`) returns a token to send as `X-Box-Office-Session`.

## Side effects

- Emails land in Mailpit at `http://localhost:8025` (`/api/v1/search?query=to:<addr>`).
- Queued work (emails, webhooks) needs a queue worker: `docker compose -f docker-compose.dev.yml exec -d backend php artisan queue:work`.
