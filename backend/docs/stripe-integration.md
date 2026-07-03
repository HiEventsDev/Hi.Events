# Stripe Payment Integration — Hi.Events

This document provides a comprehensive reference for how Stripe is integrated into the Hi.Events codebase, covering both the Laravel backend and the React (SSR) frontend.

---

## Table of Contents

1. [Overview](#overview)
2. [Dependencies](#dependencies)
3. [Environment Variables](#environment-variables)
4. [Architecture & Flow](#architecture--flow)
5. [Database Schema](#database-schema)
6. [Backend — Infrastructure Layer](#backend--infrastructure-layer)
7. [Backend — Domain Layer](#backend--domain-layer)
8. [Backend — Application Layer (Handlers)](#backend--application-layer-handlers)
9. [Backend — HTTP Layer (Actions & Routes)](#backend--http-layer-actions--routes)
10. [Backend — Webhook Handling](#backend--webhook-handling)
11. [Frontend — React Components & Queries](#frontend--react-components--queries)
12. [SaaS Mode vs. Open-Source Mode](#saas-mode-vs-open-source-mode)
13. [Multi-Platform Support](#multi-platform-support)
14. [Refund Flow](#refund-flow)
15. [Payout Reconciliation](#payout-reconciliation)
16. [Testing](#testing)

---

## Overview

Hi.Events integrates Stripe as the primary online payment provider. The integration supports:

- **Payment Intents** for checkout (no legacy charges API)
- **Stripe Connect** for SaaS mode (organizers connect their own Stripe accounts)
- **Webhooks** for asynchronous payment confirmation, refunds, payouts, and account updates
- **Multi-Platform Keys** (e.g., separate Stripe accounts for Canada (`ca`) and Ireland (`ie`) in Hi.Events cloud)
- **Automatic refunds** for expired orders
- **Application fees** charged on top of transactions in SaaS mode
- **EU VAT handling** for application fees

---

## Dependencies

### Backend (`backend/composer.json`)

| Package | Version | Purpose |
|---------|---------|---------|
| `stripe/stripe-php` | `^17.0` | Official Stripe PHP SDK |

### Frontend (`frontend/package.json`)

| Package | Version | Purpose |
|---------|---------|---------|
| `@stripe/stripe-js` | `^1.54.1` | Stripe.js browser SDK (loads Stripe, creates Elements) |
| `@stripe/react-stripe-js` | `^2.1.1` | React components (`<Elements>`, `<PaymentElement>`, hooks) |

---

## Environment Variables

Defined in `backend/.env.example`. In production, set the equivalent non-example values.

```env
# Default / open-source keys
STRIPE_PUBLIC_KEY=pk_live_...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Multi-platform: Canada (optional — SaaS only)
STRIPE_CA_PUBLIC_KEY=pk_live_...
STRIPE_CA_SECRET_KEY=sk_live_...
STRIPE_CA_WEBHOOK_SECRET=whsec_...

# Multi-platform: Ireland (optional — SaaS only)
STRIPE_IE_PUBLIC_KEY=pk_live_...
STRIPE_IE_SECRET_KEY=sk_live_...
STRIPE_IE_WEBHOOK_SECRET=whsec_...

# Which platform is treated as primary when multiple exist
STRIPE_PRIMARY_PLATFORM=ca         # 'ca' | 'ie' | (empty)

# SaaS-mode settings
APP_SAAS_MODE_ENABLED=false
APP_SAAS_STRIPE_APPLICATION_FEE_PERCENT=1.5
APP_STRIPE_CONNECT_ACCOUNT_TYPE=express   # 'express' | 'standard' | 'custom'
```

These map to `config/services.php` under the `stripe` key via Laravel's config resolution.

---

## Architecture & Flow

### Checkout Payment Flow

```
Browser                 Backend (Laravel)                   Stripe API
   |                          |                                  |
   |-- POST /stripe/payment_intent -->                           |
   |                   CreatePaymentIntentActionPublic           |
   |                          |                                  |
   |                   CreatePaymentIntentHandler                |
   |                          |--> (fetch order, account)        |
   |                          |--> StripeClientFactory           |
   |                          |      (picks correct platform key)|
   |                          |                                  |
   |                          |--> StripePaymentIntentCreationService
   |                          |      paymentIntents.create() --->|
   |                          |<-- { paymentIntentId, secret } --|
   |                          |                                  |
   |                          |--> Save stripe_payment record    |
   |<-- { client_secret, public_key, account_id } ----------    |
   |                          |                                  |
   |-- Stripe.js Elements render (user fills card details)       |
   |-- stripe.confirmPayment() --------------------------------->|
   |<-- redirect to /payment_return ------------------------------|
   |                          |                                  |
   |-- GET /stripe/payment_intent -->                            |
   |                   GetPaymentIntentActionPublic              |
   |                          |--> Retrieve PaymentIntent        |
   |                          |    from Stripe API               |
   |                          |--> If succeeded & DB not updated:|
   |                          |    PaymentIntentSucceededHandler |
   |<-- { status } ----------------------------------------     |
   |                          |                                  |
   |             (Stripe also sends webhook asynchronously)      |
   |                   POST /webhooks/stripe  ---------------<---|
   |                   StripeIncomingWebhookAction               |
   |                          |                                  |
   |                   IncomingWebhookHandler                    |
   |                          |--> PaymentIntentSucceededHandler |
   |                          |    - update order status         |
   |                          |    - activate attendees          |
   |                          |    - update product quantities   |
   |                          |    - fire OrderStatusChangedEvent|
```

---

## Database Schema

### `stripe_customers`

Caches Stripe customer records to avoid repeated API lookups.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Auto-increment |
| `name` | string | Customer name |
| `email` | string | Customer email |
| `stripe_customer_id` | string | Stripe `cus_...` ID |
| `stripe_account_id` | string (nullable) | Connected Stripe account (SaaS mode) |
| `created_at` / `updated_at` / `deleted_at` | timestamps | Standard |

### `stripe_payments`

One record per Stripe Payment Intent. Central Stripe payment record linked to an order.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `order_id` | bigint FK | Links to `orders.id` |
| `payment_intent_id` | string | Stripe `pi_...` ID |
| `connected_account_id` | string (nullable) | Stripe Connect account ID |
| `charge_id` | string (nullable) | Stripe `ch_...` ID |
| `payment_method_id` | string (nullable) | Stripe `pm_...` ID |
| `amount_received` | integer (nullable) | Amount received in minor units |
| `currency` | string | ISO currency code (uppercase) |
| `last_error` | json (nullable) | Last payment error from Stripe |
| `application_fee_gross` | integer | Application fee (gross) in minor units |
| `application_fee_net` | integer | Application fee (net) in minor units |
| `application_fee_vat` | integer | VAT on application fee in minor units |
| `application_fee_vat_rate` | decimal (nullable) | VAT rate applied |
| `stripe_platform` | string (nullable) | Platform identifier (`ca`, `ie`) |
| `payout_id` | string (nullable) | Associated Stripe payout ID |

### `stripe_payouts`

Tracks Stripe payout records for VAT reconciliation in SaaS mode.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `payout_id` | string | Stripe `po_...` ID (unique) |
| `stripe_platform` | string | Platform identifier |
| `amount_minor` | integer | Payout amount in minor units |
| `currency` | string | ISO currency code |
| `payout_date` | timestamp | Payout arrival date |
| `payout_status` | string | Stripe payout status |
| `total_application_fee_vat_minor` | integer (nullable) | Aggregated VAT on application fees |
| `total_application_fee_net_minor` | integer (nullable) | Aggregated net application fees |
| `metadata` | json (nullable) | Raw Stripe payout metadata |
| `reconciled` | boolean | Whether VAT reconciliation was completed |

### `account_stripe_platforms`

Stores per-platform Stripe Connect account info (SaaS mode only).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `account_id` | bigint FK | Hi.Events account |
| `stripe_connect_account_type` | string (nullable) | `express` / `standard` / `custom` |
| `stripe_connect_platform` | string(2) (nullable) | `ca` / `ie` / `NULL` |
| `stripe_account_id` | string (nullable) | Stripe `acct_...` ID |
| `stripe_setup_completed_at` | timestamp (nullable) | When the Connect onboarding was completed |
| `stripe_account_details` | jsonb (nullable) | Cached Stripe account capability details |

### `orders` table additions

| Column | Type | Description |
|--------|------|-------------|
| `payment_provider` | string (nullable) | `STRIPE` / `OFFLINE` — set on payment completion |

### `event_settings` additions

| Column | Type | Description |
|--------|------|-------------|
| `payment_providers` | json array | Which providers are enabled: `["STRIPE"]`, `["OFFLINE"]`, or both |

---

## Backend — Infrastructure Layer

### `StripeConfigurationService`

**File:** `app/Services/Infrastructure/Stripe/StripeConfigurationService.php`

Provides all Stripe configuration values, supporting per-platform lookups.

| Method | Description |
|--------|-------------|
| `getSecretKey(?StripePlatform)` | Returns secret key for default, `ca`, or `ie` platform |
| `getPublicKey(?StripePlatform)` | Returns publishable key for default, `ca`, or `ie` platform |
| `getPrimaryPlatform()` | Returns the configured primary platform enum value |
| `getAllWebhookSecrets()` | Returns array of all webhook secrets, sorted with primary platform first |

### `StripeClientFactory`

**File:** `app/Services/Infrastructure/Stripe/StripeClientFactory.php`

Creates `Stripe\StripeClient` instances with the correct API key.

```php
// Usage: creates a client for the specified platform (or default keys if null)
$client = $stripeClientFactory->createForPlatform($stripePlatform);
```

Throws `StripeClientConfigurationException` if the secret key is not configured for the requested platform.

---

## Backend — Domain Layer

### `StripePaymentIntentCreationService`

**File:** `app/Services/Domain/Payment/Stripe/StripePaymentIntentCreationService.php`

Handles the actual Stripe API calls for creating and retrieving Payment Intents.

Key behaviours:
- **Creates a Stripe Customer** if one does not already exist for the order's email + connected account combination (upsert pattern)
- **Calculates application fees** via `OrderApplicationFeeCalculationService` and includes `application_fee_amount` in the Payment Intent when SaaS mode is active
- **Automatic payment methods enabled** (`automatic_payment_methods.enabled = true`)
- **Stores metadata** on the Payment Intent: `order_id`, `event_id`, `order_short_id`, `account_id`, and application fee breakdown

### `StripePaymentIntentRefundService`

**File:** `app/Services/Domain/Payment/Stripe/StripePaymentIntentRefundService.php`

Issues refunds via `stripe.refunds.create()`. In SaaS mode, routes the refund to the correct connected account.

### `StripeAccountSyncService`

**File:** `app/Services/Domain/Payment/Stripe/StripeAccountSyncService.php`

Synchronises Stripe Connect account status with the local database.

| Method | Description |
|--------|-------------|
| `syncStripeAccountStatus()` | Called from webhook; updates `stripe_setup_completed_at` based on `charges_enabled && payouts_enabled` |
| `markAccountAsComplete()` | Forces account to complete state and triggers VAT setting creation for EU countries |
| `createStripeAccountSetupUrl()` | Creates a Stripe Account Link for Connect onboarding |
| `isStripeAccountComplete()` | Returns `true` if `charges_enabled && payouts_enabled` |

### `StripePaymentPlatformFeeExtractionService`

**File:** `app/Services/Domain/Payment/Stripe/StripePaymentPlatformFeeExtractionService.php`

Extracts fee breakdown (Stripe fees vs application fees) from the balance transaction on a Charge, and stores them in `order_payment_platform_fees`. Also handles currency conversion for EU VAT.

### `StripePayoutService`

**File:** `app/Services/Domain/Payment/Stripe/StripePayoutService.php`

Creates/updates payout records when a Stripe `payout.paid` or `payout.updated` event is received, aggregating VAT amounts across all charges in the payout for reconciliation.

### `StripeRefundExpiredOrderService`

**File:** `app/Services/Domain/Payment/Stripe/StripeRefundExpiredOrderService.php`

Handles the scenario where a payment succeeds but the order has already expired (past `reserved_until`). Automatically refunds the customer and sends a notification email.

### Webhook Event Handlers

Located in `app/Services/Domain/Payment/Stripe/EventHandlers/`.

| Handler | Stripe Event(s) | Description |
|---------|-----------------|-------------|
| `PaymentIntentSucceededHandler` | `payment_intent.succeeded` | Marks order as `COMPLETED` / `PAYMENT_RECEIVED`, activates attendees, updates product quantities, fires domain events, stores application fee |
| `PaymentIntentFailedHandler` | `payment_intent.payment_failed` | Updates order payment status to `PAYMENT_FAILED` |
| `ChargeSucceededHandler` | `charge.succeeded`, `charge.updated` | Extracts and stores platform fee breakdown from balance transaction |
| `ChargeRefundUpdatedHandler` | `refund.updated`, `refund.created`, `charge.refunded` | Processes refund success/failure, updates order refund status and statistics |
| `AccountUpdateHandler` | `account.updated` | Syncs Stripe Connect account status to local DB |
| `PayoutPaidHandler` | `payout.paid`, `payout.updated` | Creates/updates payout reconciliation records |

---

## Backend — Application Layer (Handlers)

### `CreatePaymentIntentHandler`

**File:** `app/Services/Application/Handlers/Order/Payment/Stripe/CreatePaymentIntentHandler.php`

Orchestrates the full payment intent creation flow:

1. Validates order session and status (`RESERVED`, not expired)
2. Loads account with `AccountStripePlatformDomainObject` and `AccountConfigurationDomainObject`
3. Resolves the correct `StripePlatform` (from account's active platform or system default)
4. If order already has a `stripe_payment` record, retrieves the existing `client_secret` instead of creating a new Payment Intent
5. Creates a new Payment Intent via `StripePaymentIntentCreationService`
6. Persists the `stripe_payments` record
7. Returns `CreatePaymentIntentResponseDTO` with `client_secret`, `public_key`, `account_id`, `stripe_platform`

### `GetPaymentIntentHandler`

**File:** `app/Services/Application/Handlers/Order/Payment/Stripe/GetPaymentIntentHandler.php`

Called from the payment return page. Retrieves the Payment Intent from Stripe and, as a safety net, manually triggers `PaymentIntentSucceededHandler` if the payment succeeded but the order DB record hasn't been updated yet (webhook may be delayed or failed).

### `IncomingWebhookHandler`

**File:** `app/Services/Application/Handlers/Order/Payment/Stripe/IncomingWebhookHandler.php`

- Validates webhook signature against all configured platform webhook secrets (tries each in order)
- Deduplicates events via Redis/cache (`stripe_event_{id}`, TTL 60 minutes)
- Routes events to the appropriate domain event handler via `switch`

**Handled Events:**
```
payment_intent.succeeded
payment_intent.payment_failed
account.updated
refund.updated / refund.created
charge.refunded
charge.succeeded / charge.updated
payout.paid / payout.updated
```

### `CreateStripeConnectAccountHandler`

**File:** `app/Services/Application/Handlers/Account/Payment/Stripe/CreateStripeConnectAccountHandler.php`

SaaS-mode only. Creates or retrieves a Stripe Connect account for an organiser, stores it in `account_stripe_platforms`, and generates the Account Link URL for onboarding.

### `RefundOrderHandler`

**File:** `app/Services/Application/Handlers/Order/Payment/Stripe/RefundOrderHandler.php`

Admin-initiated refund. Uses the payment's original `stripe_platform` to select the correct Stripe client, issues a refund, and marks the order as `REFUND_PENDING`.

---

## Backend — HTTP Layer (Actions & Routes)

### Routes

Defined in `routes/api.php`.

#### Public Routes (`/api/public/`)

| Method | Path | Handler Action | Description |
|--------|------|----------------|-------------|
| `POST` | `/events/{event_id}/order/{order_short_id}/stripe/payment_intent` | `CreatePaymentIntentActionPublic` | Create or retrieve a Stripe Payment Intent |
| `GET` | `/events/{event_id}/order/{order_short_id}/stripe/payment_intent` | `GetPaymentIntentActionPublic` | Retrieve Payment Intent status (called on payment return) |
| `POST` | `/webhooks/stripe` | `StripeIncomingWebhookAction` | Stripe webhook endpoint |

#### Authenticated Routes (`/api/` + `auth:api` middleware)

| Method | Path | Handler Action | Description |
|--------|------|----------------|-------------|
| `GET` | `/accounts/{account_id}/stripe/connect_accounts` | `GetStripeConnectAccountsAction` | List organiser's Stripe Connect accounts |
| `POST` | `/accounts/{account_id}/stripe/connect` | `CreateStripeConnectAccountAction` | Initiate Stripe Connect onboarding |
| `POST` | `/events/{event_id}/orders/{order_id}/refund` | `RefundOrderAction` | Issue a refund via Stripe |

### Action Classes

#### `CreatePaymentIntentActionPublic`

**File:** `app/Http/Actions/Orders/Payment/Stripe/CreatePaymentIntentActionPublic.php`

Returns:
```json
{
  "client_secret": "pi_xxx_secret_xxx",
  "account_id": "acct_xxx",        
  "public_key": "pk_live_xxx",
  "stripe_platform": "ca"
}
```

#### `StripeIncomingWebhookAction`

**File:** `app/Http/Actions/Common/Webhooks/StripeIncomingWebhookAction.php`

Dispatches webhook processing as a queued closure (fire-and-forget) and immediately returns `HTTP 204 No Content`. This prevents Stripe from retrying due to slow processing.

---

## Backend — Webhook Handling

### Webhook Security

The `IncomingWebhookHandler` calls `Stripe\Webhook::constructEvent()` with the raw request body and `Stripe-Signature` header. It iterates through all configured platform webhook secrets and uses the first one that validates successfully.

### Idempotency

All webhook events are cached with key `stripe_event_{event_id}` (TTL: 60 minutes). Payment intent success is additionally cached with `payment_intent_handled_{pi_id}` to prevent double-processing.

### Webhook Registration

Register your webhook endpoint in the Stripe Dashboard:
- **URL:** `https://your-domain.com/api/public/webhooks/stripe`
- **Events to listen for:**
  - `payment_intent.succeeded`
  - `payment_intent.payment_failed`
  - `charge.succeeded`
  - `charge.updated`
  - `charge.refunded`
  - `refund.created`
  - `refund.updated`
  - `account.updated` _(SaaS mode)_
  - `payout.paid` _(SaaS mode)_
  - `payout.updated` _(SaaS mode)_

---

## Frontend — React Components & Queries

### Component Hierarchy

```
Payment/index.tsx                    — main payment page
├── StripePaymentMethod/index.tsx    — Stripe tab/section
│   ├── useCreateStripePaymentIntent — fetches client_secret & public_key
│   └── Elements (stripe-js)        — wraps Stripe context
│       └── StripeCheckoutForm/index.tsx  — PaymentElement + submit logic
└── OfflinePaymentMethod/index.tsx   — offline instructions
```

### `Payment/index.tsx`

**File:** `frontend/src/components/routes/product-widget/Payment/index.tsx`

- Reads `event.settings.payment_providers` to determine which methods are available
- Defaults to Stripe if enabled, otherwise offline
- Shows method selector tabs when both are available
- Handles submit orchestration: calls Stripe's `handleSubmit` or the offline mutation

Key state:
- `activePaymentMethod: 'STRIPE' | 'OFFLINE' | null`
- `submitHandler`: a ref to the Stripe form's submit function, passed down via `setSubmitHandler`

### `StripePaymentMethod/index.tsx`

**File:** `frontend/src/components/routes/product-widget/Payment/PaymentMethods/Stripe/index.tsx`

1. Calls `useCreateStripePaymentIntent` (which POSTs to the backend)
2. Calls `loadStripe(publicKey, { stripeAccount })` once the client secret is available
3. Renders Stripe's `<Elements>` provider with the client secret and theme settings
4. Adapts the Stripe theme to the event's dark/light mode via `validateThemeSettings`

### `StripeCheckoutForm/index.tsx`

**File:** `frontend/src/components/forms/StripeCheckoutForm/index.tsx`

- Uses `useStripe()` and `useElements()` hooks
- Renders Stripe's `<PaymentElement>` with accordion layout
- On submit: calls `stripe.confirmPayment()` with a redirect `return_url` pointing to `/checkout/{eventId}/{orderShortId}/payment_return`
- Surfaces card/validation errors in an `<Alert>`
- Guards against double-payment by checking `order.payment_status`

### `useCreateStripePaymentIntent`

**File:** `frontend/src/queries/useCreateStripePaymentIntent.ts`

```typescript
// TanStack Query — fires on mount, no caching (staleTime=0, gcTime=0)
useCreateStripePaymentIntent(eventId, orderShortId)
// Returns: { client_secret, account_id, public_key, stripe_platform }
```

### `useGetOrderStripePaymentIntentPublic`

**File:** `frontend/src/queries/useGetOrderStripePaymentIntentPublic.ts`

Called from the `PaymentReturn` page to poll the payment intent status after Stripe redirects back.

---

## SaaS Mode vs. Open-Source Mode

### Open-Source Mode (`APP_SAAS_MODE_ENABLED=false`)

- Uses `STRIPE_PUBLIC_KEY` / `STRIPE_SECRET_KEY` directly
- **No** `stripe_account` option passed to Payment Intent creation
- **No** `application_fee_amount` charged
- Stripe Connect is **not** available (throws `SaasModeEnabledException`)
- Refunds work without a connected account ID

### SaaS Mode (`APP_SAAS_MODE_ENABLED=true`)

- Organizers connect their own Stripe accounts via Stripe Connect Express/Standard
- Payment Intents are created on behalf of the connected account (`stripe_account` option)
- Application fee (`APP_SAAS_STRIPE_APPLICATION_FEE_PERCENT`) is collected by the platform
- Connect account status (capabilities, requirements) is synced via `account.updated` webhooks
- VAT is calculated on application fees for EU organizers

---

## Multi-Platform Support

Hi.Events cloud uses separate Stripe accounts for different regions, controlled by `StripePlatform` enum.

**File:** `app/DomainObjects/Enums/StripePlatform.php`

```php
enum StripePlatform: string
{
    case CANADA  = 'ca';
    case IRELAND = 'ie';
}
```

The `account_stripe_platforms` table stores which platform each organizer's Connect account belongs to. `StripeConfigurationService` selects the correct API keys based on platform, falling back to defaults.

---

## Refund Flow

```
Admin → POST /events/{id}/orders/{id}/refund
         RefundOrderAction
              ↓
         RefundOrderHandler
              ├── Validate order has stripe_payment
              ├── Get stripe_platform from stripe_payments record
              ├── StripeClientFactory.createForPlatform(platform)
              ├── StripePaymentIntentRefundService.refundPayment()
              │   └── stripe.refunds.create({ payment_intent, amount })
              ├── Mark order refund_status = REFUND_PENDING
              └── (optionally) send OrderRefunded email
                       ↓
         [Stripe webhook: refund.updated / refund.created]
              ↓
         ChargeRefundUpdatedHandler
              ├── Update order total_refunded
              ├── Set refund_status → REFUNDED | PARTIALLY_REFUNDED | REFUND_FAILED
              ├── Update event statistics
              └── Create order_refunds record
```

---

## Payout Reconciliation

> Relevant only in SaaS mode with EU VAT handling enabled.

When Stripe sends a `payout.paid` or `payout.updated` event:

1. `PayoutPaidHandler` is called with the payout object
2. `StripePayoutService.createOrUpdatePayout()` aggregates all charges in the payout
3. For each charge, VAT and net amounts (already converted to settlement currency) are summed
4. Results are stored in `stripe_payouts` for accounting/reporting

---

## Testing

Unit tests are located in `tests/Unit/Services/`.

| Test File | Coverage |
|-----------|----------|
| `Infrastructure/Stripe/StripeConfigurationServiceTest.php` | Key resolution per platform |
| `Infrastructure/Stripe/StripeClientFactoryTest.php` | Client creation, missing key exception |
| `Domain/Payment/Stripe/StripePaymentPlatformFeeExtractionServiceTest.php` | Fee extraction logic |
| `Domain/Payment/Stripe/StripeAccountSyncServiceTest.php` | Account sync logic |
| `Domain/Payment/Stripe/EventHandlers/PayoutPaidHandlerTest.php` | Payout event handling |
| `Application/Handlers/Account/Payment/Stripe/CreateStripeConnectAccountHandlerTest.php` | Connect flow |
| `Application/Handlers/Account/Payment/Stripe/GetStripeConnectAccountsHandlerTest.php` | Account listing |
| `DomainObjects/Enums/StripePlatformTest.php` | Enum parsing |
| `Domain/Order/OrderPlatformFeePassThroughServiceTest.php` | Fee pass-through |

Run backend tests:
```bash
cd docker/development
docker compose -f docker-compose.dev.yml exec backend php artisan test --filter=Stripe
```

---

## Key Files Reference

| File | Description |
|------|-------------|
| `app/Services/Infrastructure/Stripe/StripeClientFactory.php` | Creates `StripeClient` with correct platform keys |
| `app/Services/Infrastructure/Stripe/StripeConfigurationService.php` | Reads Stripe config values per platform |
| `app/Services/Domain/Payment/Stripe/StripePaymentIntentCreationService.php` | Creates/retrieves Payment Intents |
| `app/Services/Domain/Payment/Stripe/StripePaymentIntentRefundService.php` | Issues refunds |
| `app/Services/Domain/Payment/Stripe/StripeAccountSyncService.php` | Stripe Connect account sync |
| `app/Services/Domain/Payment/Stripe/StripePaymentPlatformFeeExtractionService.php` | Extracts Stripe and application fees |
| `app/Services/Domain/Payment/Stripe/StripePayoutService.php` | Payout VAT reconciliation |
| `app/Services/Domain/Payment/Stripe/StripeRefundExpiredOrderService.php` | Auto-refund for expired orders |
| `app/Services/Application/Handlers/Order/Payment/Stripe/CreatePaymentIntentHandler.php` | Orchestrates Payment Intent creation |
| `app/Services/Application/Handlers/Order/Payment/Stripe/GetPaymentIntentHandler.php` | Retrieves intent + safety-net completion |
| `app/Services/Application/Handlers/Order/Payment/Stripe/IncomingWebhookHandler.php` | Routes Stripe webhook events |
| `app/Services/Application/Handlers/Order/Payment/Stripe/RefundOrderHandler.php` | Admin refund orchestration |
| `app/Services/Application/Handlers/Account/Payment/Stripe/CreateStripeConnectAccountHandler.php` | Stripe Connect onboarding |
| `app/Services/Domain/Payment/Stripe/EventHandlers/PaymentIntentSucceededHandler.php` | Fulfils order on payment success |
| `app/Services/Domain/Payment/Stripe/EventHandlers/ChargeRefundUpdatedHandler.php` | Handles refund webhook events |
| `app/Services/Domain/Payment/Stripe/EventHandlers/ChargeSucceededHandler.php` | Extracts platform fees from charges |
| `app/Http/Actions/Common/Webhooks/StripeIncomingWebhookAction.php` | HTTP action for Stripe webhooks |
| `app/Http/Actions/Orders/Payment/Stripe/CreatePaymentIntentActionPublic.php` | HTTP action: create payment intent |
| `app/Http/Actions/Orders/Payment/Stripe/GetPaymentIntentActionPublic.php` | HTTP action: get payment intent |
| `frontend/src/components/routes/product-widget/Payment/PaymentMethods/Stripe/index.tsx` | Stripe payment tab (React) |
| `frontend/src/components/forms/StripeCheckoutForm/index.tsx` | Stripe PaymentElement form (React) |
| `frontend/src/queries/useCreateStripePaymentIntent.ts` | TanStack Query hook for payment intent |
| `app/DomainObjects/Enums/PaymentProviders.php` | `STRIPE` / `OFFLINE` enum |
| `app/DomainObjects/Enums/StripePlatform.php` | `ca` / `ie` platform enum |
