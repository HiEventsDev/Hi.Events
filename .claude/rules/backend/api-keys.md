---
paths:
  - "backend/app/**/ApiKey*/**"
  - "backend/app/**/*ApiKey*.php"
  - "backend/app/Services/Domain/Auth/AuthUserService.php"
  - "backend/app/Http/Middleware/**"
  - "backend/routes/api.php"
  - "backend/config/sanctum.php"
  - "backend/tests/Feature/Http/ApiKeys/**"
  - "frontend/src/components/routes/settings/ApiKeys/**"
  - "frontend/src/components/routes/settings/ApiReference/**"
  - "e2e/tests/account/api-keys.spec.ts"
---

# API keys

- API keys are Sanctum personal access tokens owned by a user and scoped to one account (`personal_access_tokens.account_id`). A key acts as that member: their role, grants and permission overrides apply, and it can never do more. Read-only keys carry only the `read` ability; `EnforceAccountUserAccess` blocks their writes with `API_KEY_READ_ONLY` (exports and previews marked `#[AllowedForReadOnlyUsers]` stay open).
- Signed-in routes live in two groups. `auth:api,sanctum` accepts keys; `auth:api` is session-only: auth, profile and sign-in security, team, account settings and security, payouts (Stripe Connect, terminal readers, VAT) and API key management. A new route that changes who can sign in, team membership, money destinations or account security goes in the session-only group; `ApiKeyRouteExposureTest` snapshots that group, and no `MANAGE_TEAM`, `MANAGE_ACCOUNT` or `MANAGE_PAYMENTS` action may accept keys.
- Never read JWT claims with `Auth::payload()` on a route keys can reach: it throws under the Sanctum guard. Use `AuthUserService` (`getAuthenticatedAccountId`, `isImpersonating`, `getSessionTokenId`, `logout`). The access cache key for a key request is `api-key:{id}`.
- A key works only while its owner is an ACTIVE member of its account (Sanctum `authenticateAccessTokensUsing` in `AppServiceProvider`); deactivating a member deletes their keys, and so does every `UserSessionRevocationService` call (password reset, SSO link changes, enforcement), because a key must never outlive a credential reset. Revocations driven by one account's SSO settings pass that account id, so only its keys go. A voluntary password change or turning 2FA off keeps keys on purpose (as GitHub and Stripe do); the key-created email tells the owner to revoke a key they didn't create. The account's 2FA requirement and pending deletion also block keys.
- Only the SHA-256 hash and the last four characters are stored; the plain key is returned once, on create. Creating a key needs a verified email and `IdentityConfirmationService`, is refused while impersonating, and is capped at `MAX_ACTIVE_KEYS_PER_MEMBER`. Key names are restricted to a markdown-safe character set because they appear in emails.
- The in-app API reference (`/settings/api-keys/reference`) renders the committed `resources/api-reference/openapi.json` with Scalar, themed through `scalarTheme.ts`. Keep Scalar's AI agent, MCP and telemetry switched off, and load it client-only (it touches `window` on import).
