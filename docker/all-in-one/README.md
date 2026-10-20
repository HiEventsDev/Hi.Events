# Hi.Events All-in-One Docker Image

The all-in-one Docker image runs both the frontend and backend services in a single container. While it can be used in 
production, the recommended approach for production is to run the frontend and backend separately for better scalability and security.

The provided docker-compose.yml file is meant for development and testing purposes. For production, you should use
the [Docker image](https://hub.docker.com/r/daveearley/hi.events-all-in-one), or create your own Docker compose file with the 
necessary [configurations for production](https://hi.events/docs/getting-started/deploying#configuring-environment-variables).

## Quick Start with Docker

### Step 1: Clone the Repository

```bash
git clone git@github.com:HiEventsDev/hi.events.git
cd hi.events/docker/all-in-one
```

### Step 2: Copy the Environment File

```bash
cp .env.example .env
```

### Step 3: Generate the `APP_KEY` and `JWT_SECRET`

Generate the keys using the following commands:

#### Unix/Linux/MacOS/WSL
```bash
echo base64:$(openssl rand -base64 32)  # For APP_KEY
openssl rand -base64 32                 # For JWT_SECRET
```

#### Windows (Command Prompt):
```cmd
for /f "tokens=*" %i in ('openssl rand -base64 32') do @echo APP_KEY=base64:%i
for /f "tokens=*" %i in ('openssl rand -base64 32') do @echo JWT_SECRET=%i
```

#### Windows (PowerShell):
```powershell
"base64:$([Convert]::ToBase64String([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)))"  # For APP_KEY
[Convert]::ToBase64String([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32))  # For JWT_SECRET
```

### Step 4: Update the `.env` File

Update the `.env` file located in `./docker/all-in-one/.env` with the generated `APP_KEY` and `JWT_SECRET`:

```plaintext
APP_KEY=your_generated_app_key
JWT_SECRET=your_generated_jwt_secret
```

### Step 5: Start the Docker Containers

```bash
docker compose up -d
```

### Step 6: Create an Account

Visit [http://localhost:8123/auth/register](http://localhost:8123/auth/register) to create an account.

## Optional: Single Sign-On

Let people sign in with Google, Microsoft or any OpenID Connect provider (Okta, Microsoft Entra ID, Keycloak, Authentik, Zitadel…).

1. Register an app with your provider and add this redirect URI: `<APP_FRONTEND_URL>/sso/callback`, for example `https://tickets.example.com/sso/callback`.
2. Add the client ID and secret to your `.env`:
   - Google: `SSO_GOOGLE_CLIENT_ID`, `SSO_GOOGLE_CLIENT_SECRET`
   - Microsoft: `SSO_MICROSOFT_CLIENT_ID`, `SSO_MICROSOFT_CLIENT_SECRET`. In the app registration, allow work and personal accounts and add the optional `email` and `xms_edov` ID token claims.
   - Any other OpenID Connect provider: `SSO_OIDC_ISSUER`, `SSO_OIDC_CLIENT_ID`, `SSO_OIDC_CLIENT_SECRET` and a button label in `SSO_OIDC_NAME`. Set `SSO_OIDC_TRUST_EMAIL=true` only if the provider verifies every email address it issues.
3. Restart the containers, then check the setup with `docker compose exec all-in-one php /app/backend/artisan sso:status`.

To make everyone except super admins sign in through single sign-on, set `SSO_PASSWORD_LOGIN_DISABLED=true`. If your provider is ever unavailable, unset it and restart to get password sign-in back.

Before turning password sign-in off, make sure existing users can get in: they must have linked single sign-on from their profile, or your provider must vouch for their email (Google: Gmail and Workspace accounts; Microsoft: work accounts with the `xms_edov` claim; any other OpenID Connect provider: only with `SSO_OIDC_TRUST_EMAIL=true`). Anyone else is locked out until password sign-in is back on. To see how many users this affects, run this before setting `SSO_PASSWORD_LOGIN_DISABLED=true`:

```bash
docker compose exec -e SSO_PASSWORD_LOGIN_DISABLED=true all-in-one php /app/backend/artisan sso:status
```

If someone's identity provider account is compromised or replaced, remove their single sign-on logins and sign them out everywhere:

```bash
docker compose exec all-in-one php /app/backend/artisan sso:unlink-identity person@example.com
```

If password sign-in is on and their organization doesn't require single sign-on, they can then set a password with "Forgot password". Otherwise they sign in again through single sign-on, which finds their account only by an email the provider vouches for.

For an account's own single sign-on connection, a domain is normally verified with a DNS TXT record. If you have checked ownership another way, or need to move a domain that another account has verified, mark it verified for the right account. Pass `--account` with the account ID when more than one account has added the domain:

```bash
docker compose exec all-in-one php /app/backend/artisan sso:verify-domain example.com --account=1
```

The domain is suspended after seven days without its TXT record, so ask the account to keep the record in place.

---

**Production Note:**  
For production, ensure you generate unique `APP_KEY` and `JWT_SECRET` for each environment and never hardcode sensitive values.
