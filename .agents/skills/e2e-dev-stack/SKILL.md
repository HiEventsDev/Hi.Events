---
name: e2e-dev-stack
description: Run Playwright E2E specs against the running dev stack to test uncommitted changes. Use when adding or changing an E2E spec or verifying a user-facing flow end to end.
---

# Run E2E specs against the dev stack

The hermetic e2e stack bakes source into images and `docker compose up` never rebuilds them, so test uncommitted changes against the dev stack. From `e2e/`:

```bash
E2E_BASE_URL=https://localhost:8443 MAILPIT_URL=http://localhost:8025 E2E_SAAS_MODE=true npx playwright test <spec>
```

## Prerequisites

- `E2E_SAAS_MODE=true` is required: the dev stack requires email verification, and the fixture only confirms through Mailpit in SaaS mode.
- A queue worker must be running to deliver the verification emails: `docker compose -f docker-compose.dev.yml exec -d backend php artisan queue:work`.
- Superadmin-dependent specs need a one-time `php artisan dev:bootstrap --email=superadmin@e2e.test --password='SuperAdminPass123!'`.
- Seating and box office specs need `APP_LICENCE_KEY=development` in `backend/.env`.
- `@stripe` specs need `STRIPE_SECRET_KEY`, the backend's `STRIPE_WEBHOOK_SECRET` and `E2E_STRIPE_CONNECT_ACCOUNT_ID` exported. Webhooks are handled by the queue worker, so poll for the result.

See "Against the running dev stack" in `e2e/README.md` for more.
