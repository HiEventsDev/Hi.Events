# Hi.Events E2E Suite

End-to-end tests driving the real application (Laravel backend + SSR frontend + Postgres + Redis + Mailpit) with [Playwright](https://playwright.dev). Data is arranged through the REST API and flows are exercised through a real browser.

## Layout

```
e2e/
├── playwright.config.ts     Config: baseURL, reporters, retries, artifacts
├── fixtures/                Test fixtures (the extension point)
│   ├── account.fixture.ts   Registers + verifies an account (SaaS-aware)
│   ├── auth.fixture.ts       Builds the auth-cookie storage state
│   └── index.ts             `test` / `expect` with all fixtures merged
├── api/                     Typed API client for arranging data
│   ├── api-client.ts        Auth helpers + authorized ApiClient
│   ├── factory.ts           Composable seeders (live event + product, …)
│   └── types.ts
├── pages/                   Page objects (thin, role/label/testid locators)
├── utils/                   env, unique ids, mode guards, Mailpit client
└── tests/                   Specs, grouped by feature
    ├── auth/                registration
    ├── events/              event creation
    ├── checkout/            free + Stripe checkout
    └── management/          promo codes, questions, messages,
                             check-in lists, webhooks, editing a ticket
```

Every spec asserts on real page content after the action (the created item
appears in its list/table, the edited value shows), not just a URL change.

## Running the tests

### Hermetic stack (identical to CI) — one command

`run-e2e.sh` builds the images, starts the stack behind nginx on `http://localhost:8123`,
migrates, installs dependencies, runs the suite, and tears the stack down. This is exactly
what CI runs.

```bash
./e2e/run-e2e.sh                    # everything, then tear down
./e2e/run-e2e.sh --keep-stack       # leave the stack running afterwards
./e2e/run-e2e.sh --skip-stack       # run against an already-running stack
./e2e/run-e2e.sh --skip-deps        # skip npm ci / browser install (fast re-runs)
./e2e/run-e2e.sh -- --grep @smoke   # pass args through to `playwright test`
```

Each run recreates a clean stack (`docker compose down` then `up`), so a previous
partial or broken stack never leaks into the next run.

The hermetic stack is designed to run **alongside the dev stack** — it uses nginx on
`8123` and Mailpit on `8225` (the dev stack uses `8443`/`8025`), and nothing else is
published to the host. If those two ports are also taken, remap them:

```bash
E2E_HTTP_PORT=9123 E2E_MAILPIT_PORT=9225 \
  E2E_BASE_URL=http://localhost:9123 MAILPIT_URL=http://localhost:9225 \
  ./e2e/run-e2e.sh
```

### Manual (if you want to drive the steps yourself)

```bash
docker compose -f docker/e2e/docker-compose.e2e.yml up -d --wait
docker compose -f docker/e2e/docker-compose.e2e.yml exec backend php artisan migrate --force

cd e2e
npm ci
npx playwright install --with-deps chromium
npx playwright test

docker compose -f docker/e2e/docker-compose.e2e.yml down -v
```

### Against the running dev stack

The suite is data-isolated (unique emails per run), so it can target the dev stack directly.
Use `--skip-stack` so the script doesn't manage the e2e stack, and point it at the dev
stack's URLs. Note it leaves test data behind in the dev database.

```bash
E2E_BASE_URL=https://localhost:8443 MAILPIT_URL=http://localhost:8025 \
  ./e2e/run-e2e.sh --skip-stack --skip-deps
```

### Handy scripts

```bash
npm test              # run everything
npm run test:ui       # Playwright UI mode (great for authoring)
npm run test:headed   # headed browser
npm run test:smoke    # only @smoke-tagged specs
npm run report        # open the last HTML report
npm run typecheck     # tsc --noEmit
```

## Configuration

Copy `.env.example` to `.env` to override defaults. All are optional.

| Variable            | Default                  | Purpose                                              |
| ------------------- | ------------------------ | ---------------------------------------------------- |
| `E2E_BASE_URL`      | `http://localhost:8123`  | Public entry point of the stack under test           |
| `MAILPIT_URL`       | `http://localhost:8225`  | Mailpit HTTP API, for email assertions (8225 so it coexists with the dev stack's 8025) |
| `E2E_SAAS_MODE`     | `false`                  | Must match the backend's `APP_SAAS_MODE_ENABLED`     |
| `STRIPE_PUBLIC_KEY` | _(unset)_                | Stripe test-mode key; when unset the Stripe spec skips |

## Writing a new spec

1. Import the shared harness: `import { test, expect } from '../../fixtures';`
2. Arrange data through the API — reach for `api` / `account` fixtures and the
   `factory` seeders rather than clicking through setup UI.
3. Drive the flow under test with a page object; assert in the spec, not the page object.
4. Tag fast, load-bearing checks with `{ tag: '@smoke' }`.

```ts
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';

test('example', { tag: '@smoke' }, async ({ page, api, account }) => {
  const event = await createLiveEventWithFreeTicket(api, account.organizerId);
  // …drive the browser, then assert…
});
```

### Fixtures

- **`account`** (worker-scoped) — a registered, verified account with an organizer
  and an authorized `ApiClient`. Handles SaaS-mode email verification automatically.
- **`api`** (worker-scoped) — the authorized `ApiClient` (alias of `account.api`).
- **`authedPage`** — a `Page` pre-authenticated as `account` via injected auth cookie
  (no UI login). Use it for organizer-facing flows.
- **`page`** — the default unauthenticated page. Use it for public flows (checkout).
- **`mailpit`** — client for asserting on outbound email.

The `account` fixture is **worker-scoped** — one account + organizer is shared by every
spec in a worker. Specs stay isolated by creating their own event per test (via the
`factory`). If you add an **organizer-level** spec (like webhooks), don't assume a clean
organizer — use unique values and assert your own row, or create a fresh organizer for
that test.

### Selector policy

Prefer `getByRole` / `getByLabel` — Mantine emits real `<label>` associations and the
default English (Lingui) strings are stable. `data-testid` is configured
(`testIdAttribute: 'data-testid'`); add them to frontend source for interactive
elements the suite drives — buttons (open-modal, submit), menu items, and custom
widgets with no accessible label (e.g. `CustomSelect`, whose `dataTestId` prop lands
on its target and auto-derives `<id>-option-<value>` on each option). Convention:
kebab-case `<feature>-<element>`. Don't add IDs to text inputs with a unique label —
use `getByLabel(/^Label/)` (anchored regex dodges Mantine's required `*`). See the
"Test IDs (E2E)" note in the repo `CLAUDE.md`.

Watch for two things when asserting on list content: Mantine's `Truncate` hides the
full text in a tooltip past its length limit (assert a short value, or target the
visible node), and transient success toasts briefly contain the same text (target the
specific row/heading, e.g. `getByRole('heading', { name })`, not bare `getByText`).

## SaaS mode

The suite is SaaS-aware but runs against non-SaaS by default (`E2E_SAAS_MODE=false`).
To run against a SaaS stack, bring the stack up with `E2E_SAAS_MODE=true` (sets the
backend's `APP_SAAS_MODE_ENABLED`) and run with `E2E_SAAS_MODE=true`. The account
fixture then pulls the verification PIN from Mailpit and confirms the email before use.

Known gap: SaaS-mode Stripe checkout requires a connected Stripe account
(`organizer_stripe_platforms`), which cannot be onboarded headlessly. The Stripe spec
targets non-SaaS platform-account charges until a seeded-connected-account helper exists.

## CI

`.github/workflows/e2e.yml` builds the backend and frontend images (GHA layer cache),
starts the stack, runs migrations, and executes the suite. The HTML report, traces, and
stack logs are uploaded as artifacts on every run.

The Stripe checkout spec runs only when the repository secrets `STRIPE_TEST_PUBLIC_KEY`
and `STRIPE_TEST_SECRET_KEY` (Stripe **test-mode** keys) are set; otherwise it skips,
keeping fork PRs green.
