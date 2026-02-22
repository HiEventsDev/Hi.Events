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

| Variable | Required | Description |
| :--- | :--- | :--- |
| `AUTH_{PROVIDER}_CLIENT_ID` | **Yes** | The Client ID issued by your identity provider. |
| `AUTH_{PROVIDER}_CLIENT_SECRET` | **Yes** | The Client Secret issued by your identity provider. |
| `AUTH_{PROVIDER}_REDIRECT_URI` | No | The callback URL where the provider will send the user back. **Default:** `/api/v1/auth/{provider}/callback` <br>*Make sure your ID Provider marks this exact URL (with your App domain) as an authorized redirect URI.* |
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

AUTH_GOOGLE_CLIENT_ID=your-google-client-id-here.apps.googleusercontent.com
AUTH_GOOGLE_CLIENT_SECRET=your-google-client-secret-here
# Google automatically returns 'email', so no IDENTIFIER_KEY is needed.
# The UI will automatically detect "google" and use the official Google 'G' icon.
```

### Example 2: Generic Enterprise Keycloak (Custom Logo)

If your company uses Keycloak for SSO and you want to completely lock out standard username/password logins:

```env
AUTH_PROVIDERS=corp_sso
AUTH_DISABLE_DEFAULT=true

AUTH_CORP_SSO_CLIENT_ID=my-hievents-app
AUTH_CORP_SSO_CLIENT_SECRET=super-secret-string
AUTH_CORP_SSO_ISSUER_URL=https://sso.mycompany.com/realms/master
AUTH_CORP_SSO_LOGO_URL=https://mycompany.com/assets/logo-small.png
```

In this mode, users visiting `/login` will *only* see a **"Continue with Corp_sso"** button styled with your brand logo, and the standard email form will vanish.

---

### Important Notes on Security & Registration

- **No Auto-Provisioning:** Hi.Events uses an explicit-allow model. If an unknown user successfully completes OIDC authentication, but their email is not recorded in the application database, the system will **reject** their login attempt.
- **Pre-Registration Required:** You must invite or manually provision users inside Hi.Events first to let them log in via SSO.
- **Auto-Verification:** If a user exists but hasn't verified their email, successfully passing an OIDC challenge will instantly auto-verify their email address in the database.
