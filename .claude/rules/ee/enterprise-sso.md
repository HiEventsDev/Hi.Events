---
paths:
  - "backend/ee/Sso/**"
  - "frontend/src/ee/sso/**"
  - "e2e/tests/auth/enterprise-sso.spec.ts"
---

# Enterprise single sign-on

- Only per-account connections are ee (`backend/ee/Sso`, `frontend/src/ee/sso`); social login and the operator's `SSO_OIDC_*` provider stay in core (`.claude/rules/backend/sso.md`). Core calls into ee directly (`LoginService`, `EnsureTokenNotRevoked`, `CreateAccountHandler`, `SsoProviderRegistry`).
- An account's connection is a provider with key `connection-{id}` resolved by `SsoProviderRegistry::get`, so the phase 1 start/exchange flow serves it; it only accepts the LOGIN, REAUTH and TEST intents, and inactive connections only TEST.
- A connection binds tokens to itself through `aud` plus `tid` (Entra) or `hd` (Workspace). Its emails are trusted only when the provider marks them verified (`email_verified`, Entra `xms_edov`) AND the domain is verified and not suspended for that account. Never trust them through the phase 1 resolver; connection logins go through `EnterpriseSsoResolver`.
- A connection never email-links an account owner or a superadmin, and its identities can't confirm identity (REAUTH) for users who belong to other accounts: otherwise an admin who re-points the connection at their own provider could take over other users.
- Every outbound request to a provider goes through `SsrfGuard` (https, public addresses only, pinned with `CURLOPT_RESOLVE`) unless `SSO_ALLOW_PRIVATE_ISSUERS` is set; never set it on multi-tenant installs.
- Domain status is derived from `verified_at` and `failing_since` (suspended seven days after the daily check starts failing); one account per verified domain, conflicts are resolved with `sso:verify-domain`.
- Enforcement is `account_sso_connections.enforce_sso` plus the `account_users.sso_enforced` snapshot (set at enable and when someone joins, cleared only by an admin's exempt toggle). It's checked in `LoginService::completeLogin` and `EnsureTokenNotRevoked`; tokens must carry the account's `sso_connection_id`, and a token issued through a connection dies when that connection is turned off or deleted. A member the connection can't sign in (owner of any account or superadmin, with no identity on it) is not enforced, so they keep their password; the preview reports them as `sso_available: false`. A verified domain can't be removed while enforcing. The owner is never enforced: their password sign-in always asks for 2FA, emails every admin (the owner gets their own copy), and neither they nor support can turn their 2FA off while enforcing.
- Forgot-password silently sends nothing when `isPasswordResetAllowed` is false; `/users/me` exposes the same answer as `password_reset_allowed` so Security settings doesn't offer a password link that never arrives.
- Domain capture: while a connection enforces, nobody can sign up with an email on its usable domains. `SsoEnforcementService::assertSignupAllowed` throws `SsoRequiredException` from `CreateAccountHandler` (password and social signup) and before a social signup ticket is issued; the frontend sends them to SSO discovery. Invitations to other accounts are not captured.
- Tokens issued through a connection use its session length and can't be refreshed (`SSO_SESSION_EXPIRED`); the frontend re-authenticates with `prompt=none`.
- Configuration endpoints need `Permission::MANAGE_ACCOUNT`. Setup (create, edit, check, test, add or verify a domain) takes `ee.licensed:enterprise_sso` plus `FeatureFlag::ENTERPRISE_SSO`; turning the connection, enforcement or a member on checks the flag in the action. Reads, deletes and switching things off stay ungated so a lapsed licence can wind down, and sign-in through an existing connection is never gated. Any connection, on or off, counts as in use for `LicensedFeatureUsageService`, so it stays visible and removable until deleted. Owners are emailed when someone else changes the provider or enforcement.
