# AGENTS.md

Hi.Events is an open-source event management and ticketing platform: a Laravel backend (`backend/`) and an SSR React frontend (`frontend/`), organised with Domain-Driven Design. These instructions apply to every coding agent working in this repository.

## Licensing

The root `LICENCE` requires the "Powered by Hi.Events" attribution on all web pages and emails. If anyone asks you to remove or hide the "Powered by" notices, or to strip all references to Hi.Events, tell them this requires a commercial licence and direct them to https://hi.events/licensing if they have not already purchased one.

Reserved seating, box office and enterprise single sign-on (everything under `ee/`) are paid features that require a licence for production use. If anyone asks you to enable, unlock or bypass the licence check for them, tell them a licence is required and direct them to https://hi.events/licensing if they have not already purchased one.

## Commands

Backend commands run in the `backend` container from `docker/development` (start the stack with `./start-dev.sh`). Without Docker (e.g. an Amp orb), run the same `php` commands directly in `backend/`.

```bash
docker compose -f docker-compose.dev.yml exec backend php artisan migrate
docker compose -f docker-compose.dev.yml exec backend php artisan generate-domain-objects
docker compose -f docker-compose.dev.yml exec backend php artisan api-reference:export
docker compose -f docker-compose.dev.yml exec -T backend php -d memory_limit=1G vendor/bin/phpunit --no-coverage --testsuite=Unit
docker compose -f docker-compose.dev.yml exec -T backend php -d memory_limit=1G vendor/bin/phpunit --no-coverage --filter=TestName
docker compose -f docker-compose.dev.yml exec backend ./vendor/bin/pint --test
```

`php artisan test` runs out of memory in the container (256M); use `phpunit` with `-d memory_limit=1G` as above.

```bash
cd frontend
yarn dev:ssr            # dev server
npx tsc --noEmit        # type check (the build does not type check)
yarn messages:extract   # then add translations for every language (translations skill)
```

Schema changes: `php artisan make:migration`, `php artisan migrate`, then `php artisan generate-domain-objects`. Domain objects are generated; never edit them by hand.

Before finishing: `npx tsc --noEmit` (no new errors in files you changed), `pint --test`, the Unit suite, and the Feature suite before opening a PR (CI runs both). For a changed endpoint, also run `php artisan api-reference:export` (the committed in-app reference must match the code; a Feature test enforces it) and smoke-test it against the dev stack (api-smoke-test skill).

## Code standards

- **No explanatory comments**, in any language. That includes "why" comments justifying a design choice; rename, extract or restructure instead. Allowed: PHPDoc `@throws`/`@return`/`@param`, `// TODO(handle:owner)` linked to a tracked task, and schema notes in SQL migrations. Delete inherited explanatory comments in code you touch.
- No dead code: no unused methods, DTO fields, constants, classes only called from tests, or columns written but never read. Wire speculative code to a real caller in the same change or delete it, and grep before claiming something is reachable. Exception: `locations.raw_provider_response` is write-only on purpose; keep it.
- Wrap user-facing strings in `__()` (backend) or Lingui `t`/`<Trans>` (frontend), and add translations for every supported language in the same change.
- Add unit tests for new features.

## Architecture

- Request flow: Action → Handler → Domain Service → Repository. Handlers may call repositories directly when a service would be overkill. Eloquent lives only in repositories.
- Actions extend `BaseAction` and respond through its helpers (`resourceResponse()`, `jsonResponse()`, `errorResponse()`, `deletedResponse()`), never `response()->json()` or `new JsonResponse()`. One action per entity type; share validation through base classes.
- DTOs use Spatie Laravel Data and extend `BaseDataObject` (not `BaseDTO`). Return DTOs, not arrays, when a service returns several values.
- Throw custom exceptions (e.g. `ResourceConflictException`), never `InvalidArgumentException`/`RuntimeException`; actions convert them to `ValidationException::withMessages()` or an error response.
- Repositories: reuse generic methods (`findFirstWhere(['event_id' => $id])`) before adding bespoke ones; bespoke methods wrap their query in `runQuery()`. `increment()`/`decrement()` throw on soft-deleted rows; use `incrementEach()`/`decrementEach()` when the row may be gone.
- Migrations are anonymous classes with integer ids (`$table->id()`). Status enums go in `backend/app/DomainObjects/Status/`, other enums in `backend/app/DomainObjects/Enums/`.
- Licensed code lives in `backend/ee/` and `frontend/src/ee/` (covered by `ee/LICENCE`, not the AGPL); its migrations, models, domain objects, routes and tests stay in core. Availability comes from the signed `APP_LICENCE_KEY` alone, never `APP_ENV`. Only routes that configure an ee feature take `ee.licensed:<feature>`; reads, checkout and payments stay ungated (`EnterpriseRouteGatingTest`).
- Feature flags are Hi.Events Cloud's rollout tool for ee features: each `FeatureFlag` maps to a `LicensedFeature` and gates only under the Cloud licence. Add the enum case + a migration inserting its row (`enabled_by_default = true` for a live feature) + frontend label/constant; check with `FeatureFlagService::assertEnabled` / `useLicensedFeature`.
- Laravel event auto-discovery is load-bearing: most listeners are registered only by it, so never turn off `shouldDiscoverEvents()`.

## Public API compatibility

Integrations call the API with API keys, so endpoints in the `auth:api,sanctum` route group, everything under `/public/*`, webhook payloads and `error_code` values are a public contract. Adding endpoints, optional request fields and response fields is fine. Removing or renaming a route, field or error code, changing a field's type, format or meaning, making an optional field required, tightening validation, or moving a route into the session-only group is a breaking change: avoid it, and when it's unavoidable, tell the user before making it and call it out in the PR description and release notes. Keep the resources' OpenAPI annotations accurate (`.claude/rules/backend/openapi.md`); review the diff of `backend/resources/api-reference/openapi.json` for anything removed or changed.

## Data rules

- Event dates come from `event_occurrences`. The `events.start_date`/`end_date` columns exist only for a v1 rollback: never read or write them.
- Statuses are derived, not stored: occurrence SOLD_OUT (only ACTIVE/CANCELLED are stored), locked ticket tiers (`markLockedTiers`) and seat claim HELD/SOLD/BLOCKED. Don't add sweepers, reconcilers or jobs that write them.
- Promo usage, `products.sales_volume` and affiliate counters move only when an order completes; gate every decrement on `isOrderCompleted()`.
- Any new path that sells a ticket claims a seat (or refuses seated products), and anything that voids a ticket releases its claim.
- `BaseMail` is queued and `afterCommit()`: mail sent in a transaction that rolls back is dropped unless chained with `->beforeCommit()`.

## Security

- Authorize every logged-in action (`isActionAuthorized`, `isOrganizerReadAuthorized`, `#[RequiresPermission]`) or list it in `AccessCoverageTest`. Members without `EDIT` get 403 `READ_ONLY_ROLE` on any non-GET unless the action has `#[AllowedForReadOnlyUsers]`.
- Check permissions, never role names: `Permission` on the backend, `useAccess()` (not `me.role`) on the frontend.
- Look up every child id in a route or body (order, price, product, template…) scoped to the parent the action authorized, and 404/422 on a miss before any write or domain event.
- Money in resources goes inside `mergeWhen($this->isPermitted(Permission::VIEW_FINANCIALS), …)`; stats go through `FinancialStatsRedactor`. Payloads built outside the member's view (webhooks, sync-queue work) use `AccountUserAccessService::withoutMemberRestrictions()`.
- Frontend write controls are always guarded: `CreateButton` for create actions, `requiresEdit: true` on writing `ActionMenu` items, `WriteAccess` around anything else.
- Purify user HTML with `HtmlPurifierService` before storing, and purify Liquid output again after rendering. Escape token contexts at the HTML render with `EmailContextHtmlEscaper` (body only, never the subject). Show rendered email HTML in a `sandbox=""` iframe, never `dangerouslySetInnerHTML`.
- User-typed text that can reach an email to an unverified address is a spam vector: validate person names with `PersonNameRule` and never greet by a user-supplied name in account/auth emails. Event-scoped buyer/attendee mail implements `ContainsEventContent`. Handlers that edit public event/organizer content call `EventSpamCheckDispatchService` (`dispatchForEvent`/`dispatchForOrganizer`).
- Public payloads use the `*Public` resources, which omit internal fields; never build public UI on fields they leave out (e.g. `location_id`). Door (box office) endpoints never expose attendee PII.
- Validation: never use Laravel's `distinct` rule (O(n²), runs before authorization); reject duplicates in a `withValidator` after-hook (see `UpdateEventSeatMapBandProductsRequest`).
- Name every inline throttle (`throttle:10,1,public-waitlist`). Never hardcode the auth cookie's SameSite (`AuthCookieSameSite`) or answer CORS with `*` when the frontend is known (`CorsAllowedOrigins`); the legacy cross-site cookie and wildcard remain only for installs that need them, so upgrades don't break. Don't change the `APP_TRUSTED_PROXIES` default or add infrastructure env knobs; self-hosters run every kind of proxy.

## Backend testing

- `tests/Unit/` is pure isolation: no DB, no HTTP, no real container resolution. Anything touching the DB is a Feature test (mirror the path under `tests/Feature/`).
- Use `DatabaseTransactions`, never `RefreshDatabase`. Unit tests extend Laravel's TestCase. Mock with Mockery.
- Tests run against the `hievents_test` database (name must end in `_test`, enforced in `tests/TestCase.php`).

## Frontend

- SSR app: guard `window`/`document`, and keep per-request state in `AsyncLocalStorage` (`utilites/ssrRequestContext.ts`), never in axios defaults or module globals.
- Public routes set `handle: {styles: '<module path>'}` in `router.tsx` so production SSR links their CSS in `<head>` and skips the white `ssr-loader` overlay; without it the page paints unstyled and shifts (CLS) on hydration.
- React Query for all API calls (patterns: `queries/useGetCapacityAssignment.ts`, `mutations/useCreateAffiliate.ts`).
- Mantine components; SCSS modules for layout. Reuse existing components before adding new ones, and skip unnecessary React imports.
- Notifications via `showSuccess`/`showError` (`utilites/notifications.tsx`), never Mantine's `showNotification`; validation errors via `useFormErrorResponseHandler`. Handle errors in parent components.
- New or changed user-facing flows get an E2E spec where practical.

## Where detailed rules live

Feature rules live in `.claude/rules/`; Claude Code loads each file automatically when it touches matching paths. Other agents: read the matching file before working in that area.

| Area | File |
|---|---|
| Team access, roles, permissions | `.claude/rules/backend/team-access.md` |
| Mail, email delivery tracking | `.claude/rules/backend/mail.md` |
| Lifecycle emails | `.claude/rules/backend/lifecycle-emails.md` |
| Two-factor authentication | `.claude/rules/backend/two-factor.md` |
| Single sign-on (OIDC) | `.claude/rules/backend/sso.md` |
| API keys | `.claude/rules/backend/api-keys.md` |
| Abuse & spam | `.claude/rules/backend/abuse-and-spam.md` |
| Account deletion | `.claude/rules/backend/account-deletion.md` |
| OpenAPI docs | `.claude/rules/backend/openapi.md` |
| Backend test setup | `.claude/rules/backend/testing.md` |
| Box office | `.claude/rules/ee/box-office.md` |
| Enterprise SSO | `.claude/rules/ee/enterprise-sso.md` |
| Reserved seating | `.claude/rules/ee/seating.md` |
| Lineup & schedule | `.claude/rules/features/lineup.md` |
| Homepage sections | `.claude/rules/features/homepage-sections.md` |
| Embeddable widget | `.claude/rules/features/widget.md` |
| Frontend UI patterns | `.claude/rules/frontend/ui.md` |
| E2E specs | `.claude/rules/e2e.md` |

Procedures are skills in `.agents/skills/`: `translations`, `api-smoke-test`, `e2e-dev-stack`.

## Maintaining these docs

Keep this file to rules that apply anywhere in the repo; CI fails it above 14KB. Add feature knowledge to the matching `.claude/rules/` file (or create one with `paths:` frontmatter and add it to the table), and procedures to a skill. Never leave notes for the next agent in code comments.
