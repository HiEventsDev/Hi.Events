---
paths:
  - "backend/app/Services/Domain/Auth/Access/**"
  - "backend/app/Services/Infrastructure/Authorization/**"
  - "backend/app/Http/Middleware/EnforceAccountUserAccess.php"
  - "backend/app/Http/Attributes/**"
  - "backend/app/DomainObjects/Enums/Role.php"
  - "backend/app/DomainObjects/Enums/Permission.php"
  - "backend/app/DomainObjects/Enums/AccountUserAccessScope.php"
  - "backend/tests/Feature/Http/TeamAccess/**"
  - "backend/app/Http/Request/EventSettings/**"
  - "frontend/src/components/routes/event/Settings/Sections/PaymentSettings/**"
  - "frontend/src/hooks/useAccess.ts"
  - "frontend/src/components/common/TeamAccess*/**"
  - "frontend/src/components/common/WriteAccess/**"
  - "frontend/src/components/common/CreateButton/**"
  - "frontend/src/components/routes/settings/Team/**"
  - "frontend/src/components/routes/organizer/Team/**"
---

# Team access & permissions

- A restricted member with no grants has no access, never full access. An event grant also gives read-only access to its organizer (`isOrganizerReadAuthorized`); an organizer grant covers its future events too.
- Treat any value that unlocks a public page as a credential. Staff responses return `orders.short_id` only to members with EDIT + VIEW_FINANCIALS; check-in list links are shareable with door staff on purpose.
- Deliberate, don't "fix": VIEW_FINANCIALS hides money in the dashboard but doesn't stop an EDIT member from configuring outbound channels (organizer email, email templates, analytics pixels) or running the door, which shows door sales.
- Free-text fields that tell buyers where to send money are payment settings. Changing an event's `offline_payment_instructions` needs MANAGE_PAYMENTS (Admins only by default) and a session: `UpdateEventSettingsRequest::after()` returns a 422 on the field for an API key or a member without it, on both PUT and PATCH. Re-sending the saved value (or one that purifies to it) always passes, so round-trips keep working; a PUT that omits it counts as clearing it. The payment settings form shows the instructions read-only to those members and re-sends the saved value. Same pattern as GTM containers in `PartialUpdateOrganizerSettingsRequest`.
- Public "same account" checks (draft previews, test orders, check-in staff view) use `AccountUserAccessService::currentMemberCanAccessEvent/Organizer`, never a raw account id comparison.
- `AccountUserAccessService` caches access per request keyed by `AuthUserService::getAuthenticatedTokenKey()` (the JWT string, or `api-key:{id}` for API keys); never key it on decoded payload claims (resources call `isPermitted()` hundreds of times per list).
- Frontend: 403s carrying `ACCESS_RESTRICTED`, `PERMISSION_REQUIRED` or `READ_ONLY_ROLE` must never send the user to login.
