# OIDC Environment Variables Tutorial

This guide explains how to dynamically configure OpenID Connect (OIDC) or standard Socialite providers (like Google, Github, Apple, or OKTA) using environment variables.

Hi.Events uses a dynamic configuration loader (`OidcServiceProvider`). This means you don't need to change any PHP code to add a new authentication provider. You simply add its configuration to your `.env` file!

## 1. Global Authentication Settings

These master settings control how the authentication portal behaves.

| Variable | Type | Description |
| :--- | :--- | :--- |
| `AUTH_PROVIDERS` | String (Comma-separated) | **Required to enable OIDC.** A list of internal keys for the providers you want to enable. Example: `AUTH_PROVIDERS=google,microsoft,okta` |
| `AUTH_DISABLE_DEFAULT` | Boolean | Set to `true` to completely disable the standard email/password login and registration forms, forcing users to use the OIDC providers you configured. (Note: A backdoor is available via `?show_login=1` on the frontend URL). |

---

## 2. Provider-Specific Variables

For every provider key you listed in `AUTH_PROVIDERS` (e.g., `google`), you must define its configuration block using the format `AUTH_{PROVIDER}_...`.

> **Important:** The `{PROVIDER}` part of the variable name must be exactly the uppercase key from `AUTH_PROVIDERS`. For example, if you added `okta`, the variables must start with `AUTH_OKTA_`.

### 🔗 Configuring the Redirect/Callback URI in your IdP

When you set up your client application in Google, Keycloak, Okta, etc., you must securely whitelist where the IdP is allowed to send the user after a successful login.
The system dynamically generates this absolute URL under the hood. You must register the following exact callback URI in your Identity Provider:
**`https://<YOUR-APP-DOMAIN>/api/auth/<provider>/callback`**
*(Example: `https://events.mycompany.com/api/auth/google/callback`)*

| Variable | Required | Description |
| :--- | :--- | :--- |
| `AUTH_{PROVIDER}_CLIENT_ID` | **Yes** | The Client ID issued by your identity provider. |
| `AUTH_{PROVIDER}_CLIENT_SECRET` | **Yes** | The Client Secret issued by your identity provider. |
| `AUTH_{PROVIDER}_IDENTIFIER_KEY` | No | The attribute from the provider's token to use to uniquely identify the user in Hi.Events (must match an existing User column). <br>**Default:** `email` |
| `AUTH_{PROVIDER}_SCOPE` | No | The OpenID scopes you want to request. <br>**Default:** `openid email profile` |
| `AUTH_{PROVIDER}_ISSUER_URL` | No | The issuer/base URL of the OpenID Provider. Needed if you are using generic OpenID Connect providers like Keycloak or Authentik. |
| `AUTH_{PROVIDER}_LOGO_URL` | No | A direct hyperlink to an image (PNG/SVG/JPG) to show on the login button. If omitted, the system will try to smartly guess the brand logo (e.g. Google, Apple) or fallback to a standard lock icon. |
| `AUTH_{PROVIDER}_DRIVER` | No | The specific Socialite driver to use. <br>**Default:** `openid`. *Do not change this unless you are specifically targeting a Socialite provider extension instead of standard OpenID Connect.* |

---

## 3. Example Use Cases

### Example 1: Google OAuth (Using Smart Brand Icon)

If you only want Google to be used for logins, add this to your `.env`:

```env
AUTH_PROVIDERS=google
AUTH_DISABLE_DEFAULT=false

AUTH_google_DRIVER="openid"
AUTH_google_CLIENT_ID="123456789"
AUTH_google_CLIENT_SECRET="secret"
AUTH_google_ISSUER_URL="https://accounts.google.com"
AUTH_google_IDENTIFIER_KEY="email"
AUTH_google_SCOPE="openid email profile"
# Google automatically returns 'email', so no IDENTIFIER_KEY is needed.
# The UI will automatically detect "google" and use the official Google 'G' icon.
```

### Example 2: Generic

If your company uses Zitadel for SSO or another IdP and you want to completely lock out standard username/password logins:

```env
AUTH_PROVIDERS=zitadel
AUTH_DISABLE_DEFAULT=true

AUTH_zitadel_DRIVER="openid"
AUTH_zitadel_CLIENT_ID="123456789"
AUTH_zitadel_CLIENT_SECRET="secret"
AUTH_zitadel_ISSUER_URL="https://auth.example.com"
AUTH_zitadel_IDENTIFIER_KEY="email"
AUTH_zitadel_SCOPE="openid email profile"
AUTH_zitadel_LOGO_URL="https://example.com/logo.svg" # If not set, the system will try to smartly guess the brand logo (e.g. Google, Apple) or fallback to a standard lock icon.
```

In this mode, users visiting `/login` will *only* see a **"Continue with Zitadel"** button styled with your brand logo, and the standard email form will vanish.

---

### Important Notes on Security & Registration

- **No Auto-Provisioning:** Hi.Events uses an explicit-allow model. If an unknown user successfully completes OIDC authentication, but their email is not recorded in the application database, the system will **reject** their login attempt.
- **Pre-Registration Required:** You must invite or manually provision users inside Hi.Events first to let them log in via SSO.
- **Auto-Verification:** If a user exists but hasn't verified their email, successfully passing an OIDC challenge will instantly auto-verify their email address in the database.
- **PKCE & State:** Because the authentication flow runs on strictly stateless API routes, PKCE (Proof Key for Code Exchange) and state (nonce) validation are inherently disabled on the backend. The integration uses the standard authorization code flow authenticated symmetrically using your `CLIENT_ID` and `CLIENT_SECRET`.
