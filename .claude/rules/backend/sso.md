---
paths:
  - "backend/app/Services/Domain/Auth/**"
  - "backend/app/Services/Application/Handlers/Auth/**"
  - "backend/app/Http/Actions/Auth/**"
  - "backend/config/sso.php"
  - "frontend/src/components/routes/sso/**"
  - "frontend/src/components/common/SsoButtons/**"
  - "backend/app/Http/Actions/Accounts/Sso/**"
  - "backend/app/Services/Application/Handlers/Account/Sso/**"
  - "frontend/src/components/routes/settings/Account/SingleSignOn/**"
---

# Single sign-on (OIDC)

- One generic OIDC client (`Services/Domain/Auth/Sso`) serves Google, Microsoft and the operator's `SSO_OIDC_*` provider; no Socialite. Providers exist only when their client id and secret are set.
- Identities are keyed by (`issuer`, `subject`) in `user_identities`; Microsoft uses the `tid`-substituted issuer and `oid`, never `sub`, `email` or `upn`. Never write the provider email back to `users.email`.
- An email links an existing user only when the provider's email is trusted (`SsoIdentityClaimsMapper`) AND the user was verified in SaaS mode or password sign-in is disabled. Superadmins, untrusted emails and ambiguous matches get `SSO_LINK_REQUIRES_LOGIN` before any membership check, so responses can't reveal account state; never create a signup ticket for an email that already has a user. The first link by email while password sign-in is off clears the old password, trusted devices and sessions.
- Every step after `start` is bound to the browser: the state, the 2FA challenge, the account-choice ticket and the signup ticket all carry the binding hash (`SsoTicketService`, one cache entry per `SsoTicketType`). Keep that when adding a step.
- All logins go through `LoginService::beginLogin`/`completeLogin` with an `AuthContext`; local 2FA always applies. A pending identity is only attached in `completeLogin`, after 2FA and account choice, just before the token is issued.
- Sensitive self-service (link, unlink, email change, 2FA setup/disable) confirms identity with `IdentityConfirmationService`: the password, or for password-less users a `reauth_proof` bound to the user and JWT `jti`. Refuse while impersonating. A missing `auth_time` on reauth is accepted because Microsoft omits it; freshness then relies on `prompt=login` and `max_age=0`.
- `users.tokens_valid_after` revokes older JWTs (`EnsureTokenNotRevoked`): authenticated routes get 401 `SESSION_REVOKED`, public routes treat the request as anonymous, and impersonation tokens also die with the impersonator's sessions. Refresh keeps the original `iat`, so reissue with `issueFreshTokenForCurrentSession` when the current session must survive a revocation.
- `SSO_PASSWORD_LOGIN_DISABLED` is enforced server-side through `PasswordLoginPolicy` (login, register, forgot/reset, invitation); superadmins keep password sign-in as break-glass.
- Invitations never overwrite an existing user's credentials: users with an active membership and a password or identity must sign in and `POST /auth/invitation/{token}/join`. A new invitee accepting with SSO needs a provider email equal to the invited email.

## Per-account enterprise connections

- An account's connection is a provider with key `connection-{id}` resolved by `SsoProviderRegistry::get`, so the phase 1 start/exchange flow serves it; it only accepts the LOGIN, REAUTH and TEST intents, and inactive connections only TEST.
- A connection binds tokens to itself through `aud` plus `tid` (Entra) or `hd` (Workspace). Its emails are trusted only when the provider marks them verified (`email_verified`, Entra `xms_edov`) AND the domain is verified and not suspended for that account. Never trust them through the phase 1 resolver; connection logins go through `EnterpriseSsoResolver`.
- A connection never email-links an account owner or a superadmin, and its identities can't confirm identity (REAUTH) for users who belong to other accounts: otherwise an admin who re-points the connection at their own provider could take over other users.
- Every outbound request to a provider goes through `SsrfGuard` (https, public addresses only, pinned with `CURLOPT_RESOLVE`) unless `SSO_ALLOW_PRIVATE_ISSUERS` is set; never set it on multi-tenant installs.
- Domain status is derived from `verified_at` and `failing_since` (suspended seven days after the daily check starts failing); one account per verified domain, conflicts are resolved with `sso:verify-domain`.
- Enforcement is `account_sso_connections.enforce_sso` plus the `account_users.sso_enforced` snapshot (set at enable and when someone joins, cleared only by an admin's exempt toggle). It's checked in `LoginService::completeLogin` and `EnsureTokenNotRevoked`; tokens must carry the account's `sso_connection_id`, and a token issued through a connection dies when that connection is turned off or deleted. A member the connection can't sign in (owner of any account or superadmin, with no identity on it) is not enforced, so they keep their password; the preview reports them as `sso_available: false`. A verified domain can't be removed while enforcing. The owner is never enforced: their password sign-in always asks for 2FA, emails every admin (the owner gets their own copy), and neither they nor support can turn their 2FA off while enforcing.
- Forgot-password silently sends nothing when `isPasswordResetAllowed` is false; `/users/me` exposes the same answer as `password_reset_allowed` so Security settings doesn't offer a password link that never arrives.
- Domain capture: while a connection enforces, nobody can sign up with an email on its usable domains. `SsoEnforcementService::assertSignupAllowed` throws `SsoRequiredException` from `CreateAccountHandler` (password and social signup) and before a social signup ticket is issued; the frontend sends them to SSO discovery. Invitations to other accounts are not captured.
- Tokens issued through a connection use its session length and can't be refreshed (`SSO_SESSION_EXPIRED`); the frontend re-authenticates with `prompt=none`.
- Configuration endpoints need `Permission::MANAGE_ACCOUNT` and `FeatureFlag::ENTERPRISE_SSO` (SaaS only); sign-in through an existing connection is never flag-gated. Owners are emailed when someone else changes the provider or enforcement.
