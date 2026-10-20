# Kiro Session Handoff

## Project
Fork of Hi.Events at `/opt/hievents` — being made multi-tenant with per-organisation email settings.
Repo: https://github.com/scracha/Hi.Events

## Infrastructure
- Running as Docker all-in-one container: `all-in-one-all-in-one-1`
- Compose file: `/opt/hievents/docker/all-in-one/docker-compose.yml`
- Env file: `/opt/hievents/docker/all-in-one/.env`
- App URL: http://localhost:8123
- Backend source (host): `/opt/hievents/backend/`
- Frontend source (host): `/opt/hievents/frontend/`
- Backend source (container): `/app/backend/`
- Frontend source (container): `/app/frontend/`
- `sudo docker` works without password (1hr timeout configured)
- No PHP on host — use: `sudo docker exec -w /app/backend all-in-one-all-in-one-1 php artisan ...`
- After editing PHP files on host, copy into container with `sudo docker cp <src> all-in-one-all-in-one-1:<dst>`
- After editing frontend files on host, copy into container then rebuild (see Build section)
- File write tools fail trying to create `/opt/.vscode` — use bash `cat > file << 'EOF'` instead

## Hi.Events Data Model (important — terminology is confusing)
- **Account** = top-level tenant (one per company/customer)
- **Organiser** = sub-entity within an Account (branding grouping for events, NOT a separate tenant)
- **Users** belong to an Account (not to individual Organisers)
- One Account can have many Organisers and many Users
- Currently there is 1 Account (id=1) with 2 Organisers under it

## What Was Completed This Session
Full per-Account SMTP email settings feature. All files written, migrated, built and live.

### New backend files (exist on host AND in container)
- `backend/database/migrations/2026_07_03_000001_create_account_email_settings_table.php` ✅ MIGRATED
- `backend/app/DomainObjects/Generated/AccountEmailSettingDomainObjectAbstract.php`
- `backend/app/DomainObjects/AccountEmailSettingDomainObject.php`
- `backend/app/Models/AccountEmailSetting.php` — uses Laravel `encrypted` cast on smtp_password
- `backend/app/Repository/Interfaces/AccountEmailSettingRepositoryInterface.php`
- `backend/app/Repository/Eloquent/AccountEmailSettingRepository.php`
- `backend/app/Resources/Account/AccountEmailSettingResource.php` — never exposes password, returns `smtp_password_set: bool`
- `backend/app/Services/Domain/Mail/AccountMailerFactory.php` — resolves per-account mailer or falls back to .env default
- `backend/app/Http/Actions/Accounts/EmailSetting/GetAccountEmailSettingAction.php`
- `backend/app/Http/Actions/Accounts/EmailSetting/UpsertAccountEmailSettingAction.php`
- `backend/app/Http/Actions/Accounts/EmailSetting/SendTestEmailAction.php` — sends test email to authenticated user via account's configured mailer
- `backend/app/Services/Application/Handlers/Account/EmailSetting/DTO/UpsertAccountEmailSettingDTO.php`
- `backend/app/Services/Application/Handlers/Account/EmailSetting/GetAccountEmailSettingHandler.php`
- `backend/app/Services/Application/Handlers/Account/EmailSetting/UpsertAccountEmailSettingHandler.php`

### Modified backend files
- `backend/app/Models/Account.php` — added `account_email_setting()` HasOne relation
- `backend/app/Providers/RepositoryServiceProvider.php` — registered AccountEmailSettingRepository binding
- `backend/app/Services/Domain/Mail/SendOrderDetailsService.php` — uses AccountMailerFactory
- `backend/app/Services/Domain/Attendee/SendAttendeeTicketService.php` — uses AccountMailerFactory
- `backend/app/Jobs/Event/SendEventEmailJob.php` — accepts accountId, uses AccountMailerFactory
- `backend/app/Services/Domain/Mail/SendEventEmailMessagesService.php` — passes accountId to job
- `backend/routes/api.php` — added GET+POST `/accounts/{account_id}/email-settings` and POST `/accounts/{account_id}/email-settings/test`

### New frontend files
- `frontend/src/queries/useGetAccountEmailSettings.ts`
- `frontend/src/mutations/useUpsertAccountEmailSettings.ts`
- `frontend/src/mutations/useSendTestEmail.ts`
- `frontend/src/components/routes/account/ManageAccount/sections/EmailSettings/index.tsx`

### Modified frontend files
- `frontend/src/types.ts` — added `AccountEmailSettings` interface
- `frontend/src/api/account.client.ts` — added `getEmailSettings()` and `updateEmailSettings()`
- `frontend/src/components/routes/account/ManageAccount/index.tsx` — added Email tab (admin-only, IconMail)
- `frontend/src/router.tsx` — added `/account/email-settings` route

## Build Commands
After any PHP change on host:
```bash
sudo docker cp /opt/hievents/backend/<file> all-in-one-all-in-one-1:/app/backend/<file>
sudo docker exec -w /app/backend all-in-one-all-in-one-1 php artisan optimize:clear
```

After any frontend change on host:
```bash
sudo docker cp /opt/hievents/frontend/<file> all-in-one-all-in-one-1:/app/frontend/<file>
sudo docker exec -w /app/frontend all-in-one-all-in-one-1 yarn messages:extract
sudo docker exec -w /app/frontend all-in-one-all-in-one-1 yarn messages:compile
sudo docker exec -w /app/frontend all-in-one-all-in-one-1 yarn build:ssr:client
sudo docker exec -w /app/frontend all-in-one-all-in-one-1 yarn build:ssr:server
sudo docker exec all-in-one-all-in-one-1 sh -c 'kill $(pgrep -f "node server.js")'
# supervisor auto-restarts the node process
```

Note: `yarn build` fails because it calls `npm` which isn't in the container. Run the steps individually as above.

## Current Outstanding Work / Next Steps

### 1. Creating a second Account (to test multi-tenancy)
Registration is disabled (`APP_DISABLE_REGISTRATION=true` in `/opt/hievents/docker/all-in-one/.env`).
To create a new account:
```bash
# Temporarily enable registration
sed -i 's/APP_DISABLE_REGISTRATION=true/APP_DISABLE_REGISTRATION=false/' /opt/hievents/docker/all-in-one/.env
sudo docker restart all-in-one-all-in-one-1
# ... register new account in browser at http://localhost:8123 ...
# Re-disable
sed -i 's/APP_DISABLE_REGISTRATION=false/APP_DISABLE_REGISTRATION=true/' /opt/hievents/docker/all-in-one/.env
sudo docker restart all-in-one-all-in-one-1
```

### 2. Verify email settings UI
- Log in as admin → Account Settings → Email tab should be visible
- Configure SMTP → save → send a test order to verify routing

### 3. Potential future work discussed
- Per-Organiser SMTP settings (currently per-Account, which is the correct tenant boundary)
- User isolation to specific Organisers (would need role/permission system at Organiser level)
- These were NOT started — just discussed

## Key Architectural Decisions Made
- SMTP password stored encrypted at rest using Laravel `encrypted` cast (tied to APP_KEY)
- Password never returned to frontend — API returns `smtp_password_set: bool` instead
- Sending blank password on save = "keep existing password" (no accidental wipes)
- `AccountMailerFactory` builds a named dynamic mailer per account at runtime, falls back to .env default if no custom SMTP configured or smtp_enabled=false

---

## Session 2: Admin Account Approval Feature (completed)

### What Was Built
When `APP_REQUIRE_ACCOUNT_APPROVAL=true`, new account registrations require admin approval:
1. User registers → account is created with `approved_at = NULL`
2. User sees "pending approval" page, cannot log in
3. Admin email (`APP_ADMIN_EMAIL`) receives an approval email with a magic link
4. Admin clicks link → frontend page calls `GET /api/accounts/approve?token=...`
5. Account gets `approved_at` set → user receives their email confirmation → can log in

### New env vars (in docker-compose.yml and .env)
- `APP_REQUIRE_ACCOUNT_APPROVAL=true` — enables the approval gate
- `APP_ADMIN_EMAIL=admin@example.com` — receives approval request emails

### New backend files
- `database/migrations/2026_07_06_000001_add_approved_at_to_accounts.php` ✅ MIGRATED
- `app/Http/Actions/Accounts/Approval/ApproveAccountAction.php`
- `app/Mail/Account/AccountApprovalRequestEmail.php`
- `resources/views/emails/admin/account-approval-request.blade.php`

### Modified backend files
- `app/DomainObjects/Generated/AccountDomainObjectAbstract.php` — added `approved_at` field
- `app/Services/Application/Handlers/Account/CreateAccountHandler.php` — sends approval email instead of user confirmation when approval required
- `app/Services/Domain/Auth/LoginService.php` — blocks login for unapproved accounts
- `app/Http/Actions/Accounts/CreateAccountAction.php` — returns pending_approval response instead of auto-login
- `config/app.php` — added `require_account_approval` and `admin_email` config entries
- `routes/api.php` — added `GET /accounts/approve` public route
- `docker/all-in-one/docker-compose.yml` — added env var pass-through
- `docker/all-in-one/.env` — added new env vars

### New frontend files
- `src/components/routes/auth/PendingApproval/index.tsx` — "pending approval" page after registration
- `src/components/routes/admin/ApproveAccount.tsx` — admin approval landing page (calls API)

### Modified frontend files
- `src/components/routes/auth/Register/index.tsx` — redirects to pending-approval page when response indicates pending
- `src/router.tsx` — added `/admin/approve-account` and `/auth/pending-approval` routes

### Verified end-to-end flow
- Registration returns `{ pending_approval: true }` ✅
- Login blocked with clear message ✅
- Approval endpoint approves account and triggers user confirmation email ✅
- Login works after approval ✅

### Additional fixes in Session 2
- Added `ResendApprovalRequestAction` — `POST /accounts/resend-approval` (public, rate-safe)
- Rewrote Login component to show actual error messages from API
- Login shows orange "pending approval" banner with "Resend Approval Request" button when account is unapproved
- Fixed approval page route (was missing from router.tsx)
- Fixed comma syntax error in router.tsx
- Fixed `CreateAccountHandler` to not verify email/account when approval is required

### Important: Container rebuild workflow
When `docker compose up --force-recreate` is used, ALL `docker cp` files are lost.
The full deploy sequence after a recreate is:
1. Copy all backend PHP files into container
2. Copy all frontend source files into container
3. `php artisan migrate --force`
4. `php artisan optimize:clear`
5. `yarn messages:compile` (extract may fail with exit 1 but that's OK)
6. `yarn build:ssr:client`
7. `yarn build:ssr:server`
8. Kill node process: `kill $(pgrep -f "node server.js")` (supervisor restarts it)
