---
paths:
  - "backend/app/**/OAuth/**"
  - "backend/app/**/*OAuth*.php"
  - "backend/app/**/Oauth*.php"
  - "backend/app/Http/Middleware/AddMcpBearerChallenge.php"
  - "backend/tests/Feature/OAuth/**"
  - "frontend/src/components/routes/oauth/**"
  - "frontend/src/components/routes/settings/ApiKeys/ConnectedApps.tsx"
  - "docker/*/nginx/nginx.conf"
---

# OAuth for the MCP server

- Hi.Events is its own small OAuth 2.1 authorization server for `/mcp`. It is hand-rolled, not Passport: authorization code + PKCE (S256 only), refresh rotation, Dynamic Client Registration and RFC 7009 revocation. Public clients only (`token_endpoint_auth_method: none`). CIMD is not supported yet; Claude falls back to DCR.
- Access tokens are ordinary Sanctum `personal_access_tokens` rows (1h, `hie_` prefix) with `oauth_connection_id` set, so every API-key safeguard applies. Anything that lists, counts, reminds about or deletes API keys one at a time must filter `oauth_connection_id IS NULL` (key list, cap, expiry reminders, single-key delete).
- An `oauth_connection` (user + account + client + access) owns its access and refresh tokens (FK cascades). Every API-key revocation also deletes connections: `UserSessionRevocationService`, `ApiKeyService::revokeAllForMember`, both anonymizers and `AccountHardDeletionService`. Otherwise a refresh token re-mints access after a credential reset.
- Refresh tokens rotate on every use and last 90 days, sliding. Reusing a rotated token more than `REFRESH_REUSE_GRACE_SECONDS` later revokes the whole connection. Replaying an authorization code revokes the connection that code created. Revocation runs outside the refresh transaction, so the delete isn't rolled back by the `invalid_grant` that follows.
- Pending authorization requests (10 min, claimed once via `cache->add` so two approvals can't both issue a code) and codes (60 s, single use via `cache->add`) live in the cache (`OAuthAuthorizationStore`). The connection is created on code exchange, not on approval, so an abandoned code leaves nothing behind. Exchange replaces any existing connection for the same user, account and client, and re-checks the connection cap.
- `PruneOAuthDataJob` drops expired refresh tokens, connections left without one, and clients with no connections idle for `OAuthPruneService::IDLE_CLIENT_DAYS` (by `last_used_at`, else `created_at`). Clients cache their DCR `client_id` and an unknown one dead-ends on `/connect?error=invalid_client`, so never prune a recently used client.
- `/oauth/authorize` never redirects to a URI the client didn't register; unknown clients and bad redirect URIs go to `{frontend}/connect?error=…`. Later errors go back to the client with `state` and `iss`. Loopback redirects match on any port; everything else matches exactly. `OAuthRedirectUriPolicy` allows https, loopback http and app schemes, never `javascript:`/`data:`/`file:`.
- Approval (`/connect`, session only) needs a verified email and `IdentityConfirmationService`, is refused while impersonating, is capped at `OAuthTokenService::MAX_CONNECTIONS_PER_MEMBER` connections per member (other clients only), and gives READ_WRITE only to members with EDIT. DCR `client_name`s are cleaned to the API-key name charset because they appear on the consent screen and in `OAuthAppConnectedMail`. "Verified" is only `OAuthRedirectUriPolicy::VERIFIED_HOSTS`.
- Build every OAuth URL through `OAuthEndpoints`, from the URL-generator root plus a trusted `X-Forwarded-Prefix`, never from `config('app.url')`/`api_url`: Cloud sets `APP_URL` to the frontend, dev forces the root to the API, and the all-in-one nginx strips `/api` and sends `X-Forwarded-Prefix: /api`. `/mcp` drops `laravel/mcp`'s `AddWwwAuthenticateHeader` and uses `AddMcpBearerChallenge`.
- With a path issuer (`https://host/api`), RFC 8414 discovery requests `https://host/.well-known/oauth-authorization-server/api` at the site root. Both nginx configs route `/.well-known/(oauth-authorization-server|oauth-protected-resource|openid-configuration)` to the backend; a custom proxy must do the same.
- The consent page validates the `request` id (`^[A-Za-z0-9]{40}$`) and encodes it before building API paths; the routes constrain `{request_id}` with `OAuthAuthorizationStore::REQUEST_ID_PATTERN`. Never put an unvalidated query value into an API path (client-side path traversal).
- The consent page lives at `/connect`, not `/oauth/...`: the frontend auth interceptor treats any path containing "auth" as public and would skip the login redirect.
- OAuth actions are excluded from the generated API reference (`UNDOCUMENTED_ACTION_NAMESPACES`).
