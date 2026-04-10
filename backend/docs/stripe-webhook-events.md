# Stripe Webhook Events Reference

## Summary Table

| Event | Listening | Needed By | Upstream Handler | Fix Branch | Action |
|---|---|---|---|---|---|
| `account.updated` | Yes | SaaS | `AccountUpdateHandler` | - | SaaS-only. Harmless noise in self-hosted (logs an error). |
| `charge.failed` | No | None | - | - | Not needed. Covered by `payment_intent.payment_failed`. |
| `charge.refund.updated` | No | None | - | - | Not needed. Redundant with `refund.updated`. |
| `charge.refunded` | Yes | Both | **None** | This PR | **Bug.** Received but dropped. Fix adds handler. |
| `charge.succeeded` | No | SaaS | `ChargeSucceededHandler` | - | Extracts platform fees. SaaS-only value. |
| `charge.updated` | No | SaaS | `ChargeSucceededHandler` | - | Re-extracts platform fees. SaaS-only value. |
| `customer.created` | No | None | - | - | Not needed. Hi.Events has no customer registry. |
| `payment_intent.created` | No | None | - | - | Not needed. Hi.Events creates the PI itself via API. |
| `payment_intent.payment_failed` | Yes | Both | `PaymentIntentFailedHandler` | - | **Essential.** Sets order payment_status to PAYMENT_FAILED. |
| `payment_intent.succeeded` | Yes | Both | `PaymentIntentSucceededHandler` | - | **Critical.** Completes order, activates attendees, updates quantities. |
| `refund.created` | Yes | Both | **None** | This PR | **Bug.** May arrive with status:succeeded and be the only signal. |
| `refund.updated` | Yes | Both | `ChargeRefundUpdatedHandler` | - | **Essential.** Updates refund_status, total_refunded, event stats. |

**Legend:**
- **Listening** = Configured in Stripe dashboard webhook subscription
- **Needed By** = `SaaS` (Connect/multi-tenant only), `Non-SaaS` (self-hosted only), `Both`, or `None`
- **Upstream Handler** = Handler in upstream Hi.Events code (develop branch)
- **Fix Branch** = This PR adds the missing handler

---

## Payment Flow

When a customer completes checkout:

1. Frontend calls `confirmCardPayment()` via Stripe.js -- payment processes **synchronously** from customer's perspective
2. Backend order stays in `RESERVED` / `AWAITING_PAYMENT` until webhook confirms
3. `payment_intent.succeeded` webhook fires -- `PaymentIntentSucceededHandler` marks order `COMPLETED` / `PAYMENT_RECEIVED`, activates attendees, updates product quantities
4. Without this webhook, **paid orders stay in AWAITING_PAYMENT forever**

## Refund Flow

When admin cancels/refunds from Hi.Events:

1. `CancelOrderHandler` calls `RefundOrderHandler` which calls Stripe Refund API
2. Order set to `REFUND_PENDING` immediately
3. Stripe fires `refund.created` -- **dropped** (no handler in upstream)
4. Stripe fires `refund.updated` with `status: succeeded` -- `ChargeRefundUpdatedHandler` updates `total_refunded`, sets `refund_status` to `REFUNDED` or `PARTIALLY_REFUNDED`

When someone refunds from the Stripe dashboard directly:

1. Stripe fires `charge.refunded` -- **dropped** (no handler in upstream)
2. Stripe fires `refund.created` -- **dropped** (no handler in upstream)
3. Stripe fires `refund.updated` -- handled, but may not fire in all cases
4. Hi.Events never learns about the refund if only `charge.refunded` fires

---

## Event Details

### account.updated

- **Stripe purpose:** Stripe Connect account status changed (onboarding complete, verification updated, capabilities changed).
- **Hi.Events handler:** `AccountUpdateHandler` -- syncs Connect account status via `StripeAccountSyncService`.
- **Self-hosted relevance:** None. This is a SaaS/Connect-only event. No `account.updated` events were generated during testing. Could be removed from the webhook subscription in self-hosted deployments.

### charge.failed

- **Stripe purpose:** A charge attempt failed (bad card number, insufficient funds, expired card, etc.).
- **Hi.Events handler:** None. Not in `$validEvents` list.
- **Why it's fine:** `payment_intent.payment_failed` covers the same failure at the PaymentIntent level, which is what Hi.Events tracks. The charge-level event is redundant.

### charge.refund.updated

- **Stripe purpose:** A refund object attached to a charge was updated (status transition during processing).
- **Hi.Events handler:** None. Not in `$validEvents` list.
- **Why it's fine:** Redundant with `refund.updated`, which Hi.Events already handles via `ChargeRefundUpdatedHandler`.

### charge.refunded

- **Stripe purpose:** A charge was refunded (fully or partially). Fires for ALL refunds regardless of where they were initiated (Hi.Events API call or Stripe dashboard).
- **Hi.Events handler:** **None in upstream.** The event is received (webhook is subscribed) but silently dropped because it's not in the `$validEvents` list.
- **Fix:** This PR adds `Event::CHARGE_REFUNDED` to `$validEvents` and a `handleChargeRefunded()` method that extracts refund objects from the charge and passes each to `ChargeRefundUpdatedHandler`.
- **Impact of bug:** Refunds initiated from the Stripe dashboard may not update Hi.Events order status.

### charge.succeeded

- **Stripe purpose:** A charge was successfully created against a payment method. Contains platform fee info for Connect payments.
- **Hi.Events handler:** `ChargeSucceededHandler` -- extracts and stores platform fee data from the charge via `StripePaymentPlatformFeeExtractionService`.
- **Self-hosted relevance:** Low. The handler runs but finds no platform fees in self-hosted mode. No harm, no value.

### charge.updated

- **Stripe purpose:** Charge metadata updated (dispute info added, receipt URL generated, description changed).
- **Hi.Events handler:** Routes to the same `ChargeSucceededHandler`. Re-extracts platform fee data.
- **Self-hosted relevance:** Low. Same as `charge.succeeded`.

### customer.created

- **Stripe purpose:** A new Stripe Customer object was created during checkout.
- **Hi.Events handler:** None. Not in `$validEvents` list.
- **Why it's fine:** Hi.Events doesn't maintain a customer registry. Orders are standalone with email/name stored directly on the order. No need to track Stripe customer IDs.

### payment_intent.created

- **Stripe purpose:** A PaymentIntent was created (checkout process started, amount reserved).
- **Hi.Events handler:** None. Not in `$validEvents` list.
- **Why it's fine:** Hi.Events creates the PaymentIntent itself via `StripePaymentIntentCreationService` and stores the `payment_intent_id` in the `stripe_payments` table. It already knows.

### payment_intent.payment_failed

- **Stripe purpose:** Customer submitted payment but it failed (card declined, authentication failed, processing error).
- **Hi.Events handler:** `PaymentIntentFailedHandler` -- sets `payment_status` to `PAYMENT_FAILED` on the order, updates Stripe payment record, fires `OrderStatusChangedEvent`.
- **Importance:** **Essential.** Without this, failed payments leave orders stuck in `AWAITING_PAYMENT`.

### payment_intent.succeeded

- **Stripe purpose:** Payment was successfully captured. Funds will be available after settlement.
- **Hi.Events handler:** `PaymentIntentSucceededHandler` -- marks order `COMPLETED` / `PAYMENT_RECEIVED`, activates attendees (AWAITING_PAYMENT -> ACTIVE), updates product quantities, records application fees, fires `OrderStatusChangedEvent` and `ORDER_CREATED` domain event.
- **Importance:** **The most critical webhook.** Without it, paid orders remain in `RESERVED` / `AWAITING_PAYMENT` permanently, attendees are never activated, and product quantities are never decremented.

### refund.created

- **Stripe purpose:** A refund object was created. May already have `status: succeeded` for instant refunds.
- **Hi.Events handler:** **None in upstream.** Received (webhook is subscribed) but dropped.
- **Fix:** This PR routes this to `ChargeRefundUpdatedHandler`.
- **Why it matters:** For fast-processing refunds, `refund.created` may arrive with `status: succeeded` and be the only event before `refund.updated`. If dropped, there's a timing window where the refund completion signal is lost.

### refund.updated

- **Stripe purpose:** A refund's status changed, typically `pending` -> `succeeded` or `pending` -> `failed`.
- **Hi.Events handler:** `ChargeRefundUpdatedHandler` -- increments `total_refunded` on the order, sets `refund_status` to `REFUNDED` (full) or `PARTIALLY_REFUNDED`, creates an `order_refunds` record, updates event statistics, fires `ORDER_REFUNDED` domain event.
- **Importance:** **Essential.** This is the handler that transitions orders from `REFUND_PENDING` to `REFUNDED`. Without it, refunds stay stuck at `REFUND_PENDING` with `total_refunded = 0.00`.

---

## Known Issues

### Refunds stuck in REFUND_PENDING

**Symptom:** Order shows `refund_status: REFUND_PENDING` and `total_refunded: 0.00` even though Stripe dashboard shows refund completed.

**Possible causes:**
1. Webhook endpoint not configured or `STRIPE_WEBHOOK_SECRET` not set -- Stripe can't deliver events
2. `refund.updated` event delivery failing -- check Stripe dashboard Event Deliveries tab for HTTP errors
3. Webhook signature verification failing -- handler throws `SignatureVerificationException` before processing
4. `refund.created` arrives with `status: succeeded` but is dropped (no handler), and `refund.updated` never fires or arrives later

**Diagnosis:** Check Stripe Dashboard -> Developers -> Webhooks -> Event Deliveries for the specific refund events and their HTTP response codes.

### Stripe Dashboard refunds not detected

**Symptom:** Refund issued directly from Stripe dashboard, Hi.Events order status unchanged.

**Root cause:** Upstream code doesn't handle `charge.refunded` or `refund.created`. The `refund.updated` handler exists but may not fire for all refund paths.

**Fix:** Add handlers for both `charge.refunded` and `refund.created` in `IncomingWebhookHandler`.

---

## Webhook Configuration

### Production

- **Endpoint:** `https://yourdomain.com/api/public/webhooks/stripe`
- **Signing secret:** Set in backend `.env` as `STRIPE_WEBHOOK_SECRET=whsec_...`
- **Route:** `POST /api/public/webhooks/stripe` -> `StripeIncomingWebhookAction`

### Local Development

Stripe can't reach localhost. Use Stripe CLI to forward events:

```bash
brew install stripe/stripe-cli/stripe
stripe login
stripe listen --forward-to https://localhost:8443/api/public/webhooks/stripe --skip-verify
# Copy the whsec_... secret printed by the CLI
# Set STRIPE_WEBHOOK_SECRET=whsec_... in backend/.env
# Restart backend container
```

---

*Generated 2026-04-09.*
