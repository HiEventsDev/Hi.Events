---
paths:
  - "backend/app/Services/Domain/Auth/**"
  - "backend/app/Services/Application/Handlers/Auth/**"
  - "backend/app/Http/Actions/Auth/**"
  - "backend/config/sso.php"
  - "frontend/src/components/routes/sso/**"
  - "frontend/src/components/common/SsoButtons/**"
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
- Per-account enterprise connections are licensed ee code: see `.claude/rules/ee/enterprise-sso.md`.
