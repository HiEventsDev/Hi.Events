# Design

## Context

See `proposal.md` for motivation. The facts below were read from the code and shape every decision.

- **Ticket identity.** The QR code is `attendees.public_id` and nothing else (`frontend/src/components/common/AttendeeTicket/index.tsx`). Check-in looks it up live, scoped to the event, so replacing it invalidates the old code immediately. `public_id` is only written at creation; a self-service email change replaces `short_id` (the link) but not `public_id`.
- **Access.** There are no attendee accounts. The order link (`orders.short_id`) authorises every self-service action on every ticket in the order. The ticket link (`attendees.short_id`) is view-only. `ticket_lookup_tokens` gives a 24-hour emailed link that matches `orders.email` only.
- **Refunds.** `StripePaymentIntentRefundService` refunds an amount against the order's payment intent, on the organizer's connected account in SaaS mode. The application fee is never refunded. `orders.refund_status = REFUND_PENDING` blocks a second refund until the webhook lands (`ChargeRefundUpdatedHandler`). Refunds have no link to an attendee.
- **Stripe behaviour** (checked against Stripe's docs): processing fees are not returned on refund; a card refund is held pending when the account's available balance is short and fails after 45 days; refunds can only go to the original payment method; card refunds have no age limit.
- **Card fee record.** Stripe's actual fee for a charge is written to `order_payment_platform_fees` by `ChargeSucceededHandler`, on a webhook separate from `payment_intent.succeeded`, and only once the balance transaction is available.
- **Stock.** `AvailableProductQuantitiesFetchService` computes availability as capacity minus sold minus live reserved orders, across the price, capacity assignments and the occurrence. Results are cached. It is called by public checkout, the waitlist, box office, manual attendee creation and admin attendee edits.
- **Waitlist.** An offer creates a real `RESERVED` order through `OrderManagementService` and `OrderItemProcessingService`, triggered by `CapacityChangedEvent(INCREASED)`.
- **Add-ons.** `product_addons` links product to product. Nothing links an add-on line to a specific ticket, and the parent check runs only at order creation.
- **Repo rules that bind this design:** statuses are derived, not stored, with no sweepers that write them; counters move only on completed orders; anything that voids a ticket releases its seat claim; public payloads and `/public/*` are a contract; user-typed text reaching unverified addresses is a spam vector; no dead columns.

## Goals / Non-Goals

**Goals:**

- The organizer's net position after a resale is never lower than if the resale had not happened. The one exception, a later chargeback on the replacement payment, is set out under Risks.
- The platform never receives, holds or pays out resale money.
- A previous holder's QR code and link stop working the moment a ticket changes hands.
- A forwarded order link alone cannot move a ticket or its money.
- No new stored status that a job must keep in sync; correctness at the door and at checkout never depends on a scheduled job having run.
- The replacement buyer's checkout is the ordinary checkout.

**Non-Goals:**

- Seated-ticket resale, free-ticket release, payouts to sellers, price-setting by sellers, buyer surcharges.
- Stripe dispute handling. None exists today; this change does not add it.
- Wallet pass revocation (the feature is not merged).
- Attendee accounts.

## Decisions

### 1. Who controls a ticket

A verified email controls a ticket when it is the ticket's attendee email, or it is the order's email and the ticket has never been transferred. A new nullable `attendees.transferred_at` records the last completed transfer; non-null means the order's buyer has lost control of that ticket.

- Transfer may be started by anyone who controls the ticket.
- Resale may only be started by the order's email, and only when `transferred_at` is null, because the refund can only go to the original payer's card.

*Alternative considered:* moving a transferred attendee to a new order. Rejected: it would split financial records, statistics and invoices for no gain.

### 2. Verified self-service session

New table `self_service_verifications` (email, hashed code, attempts, code expiry, hashed session token, session expiry).

- Request: the caller names the order or ticket link they hold; the server sends a six-digit code to the email on file for it and returns only a masked address. The caller never supplies an email, so the endpoint cannot be used to mail arbitrary addresses.
- Confirm: code valid for 10 minutes, 5 attempts, then exchanged for a session token valid for 30 minutes, bound to that email. The frontend keeps it in `sessionStorage` (guarded for SSR) and sends it as a header.
- A valid `ticket_lookup_tokens` token can be exchanged for a session directly, since it already proves control of the email.
- Sensitive handlers resolve the session's email and apply decision 1. Named throttles per link and per IP on request and confirm.

*Alternative considered:* a magic link instead of a code. Rejected: it takes the user out of the page mid-flow; the lookup link already covers the "arrive verified" case.

### 3. Editing an email stays, but is no longer a back door

The existing self-service attendee email edit keeps its meaning: the buyer re-assigns who a ticket is for and keeps control (`transferred_at` stays null). Three changes close the gaps:

- Changing an **attendee** email requires a verified session.
- Changing the **order** email requires a verified session too. Without this, a person holding only a forwarded order link could set the order email to their own address, receive the code there and then transfer every ticket. Both are breaking changes to public endpoints (see proposal).
- An attendee email change replaces `public_id` as well as `short_id`.

The organizer's attendee edit, when it changes the email, does the same replacement, sends the ticket to the new address, notifies the old one and writes an audit entry. Both paths dispatch `ATTENDEE_UPDATED`.

A shared `AttendeeCredentialRotationService` generates a new `short_id` and a `public_id` that is unique within the event, and is the single place wallet-pass revocation will hook into later. Audit entries record that credentials were replaced but never store the values.

### 4. Transfer data model and lifecycle

New table `ticket_transfers`: event, order, attendee, sender email, recipient email, hashed claim token, status, locale, `expires_at`, `accepted_at`, timestamps. A partial unique index allows one `PENDING` transfer per attendee.

- Stored statuses: `PENDING`, `ACCEPTED`, `CANCELLED`, `DECLINED`, `EXPIRED`. Expiry is derived on every read (`PENDING` and past `expires_at`); `expires_at` is the earlier of 72 hours and the transfer cutoff. No job writes an expired status: a stale `PENDING` row is only marked `EXPIRED` when the holder starts a new transfer, so the one-pending-per-ticket index stays free.
- While pending, the sender's ticket works normally. Acceptance re-checks eligibility under an attendee row lock, so a ticket checked in or cancelled in the meantime is refused.
- Acceptance, in one transaction: update name, email and locale; replace per-attendee question answers with the recipient's; rotate credentials (decision 3); set `transferred_at`; mark the transfer accepted; write the audit entry. After commit: ticket mail to the recipient, confirmation to the sender, a notice to the buyer if the sender was not the buyer, and the `ATTENDEE_UPDATED` and `attendee.transferred` events.
- The number of transfers a ticket has had is counted from `ACCEPTED` rows; there is no counter column.
- Abuse limits: the event must be `LIVE`; a ticket may have at most 5 transfers started in 24 hours; named throttles apply per link, per IP and per recipient address. The invitation goes to an unverified address, so it carries no sender-typed text and greets no one by name. All new mails implement `ContainsEventContent`. Recipient names go through `PersonNameRule`.
- A ticket with a pending transfer cannot be listed for resale, and a listed ticket cannot be transferred.

### 5. Public payload for transferred tickets

A ticket whose `transferred_at` is set and whose attendee email differs from the order email is left out of the order payload's `attendees` list and summarised in a new `transferred_tickets` list (ticket type, masked recipient email, date). This removes the QR value and ticket link from the buyer's order page and from "print all tickets" without changing the shape of any existing field. A ticket transferred back to the buyer's own address stays in `attendees`.

*Alternative considered:* keeping the attendee in the list with `public_id` and `short_id` nulled. Rejected: it changes the type of existing public fields.

### 6. Resale listing data model

New table `ticket_resale_listings`: event, attendee, seller order, product price, occurrence, `status`, `min_unit_gross`, `estimated_refund`, `connected_account_id`, `claimed_by_order_id`, `withdrawal_requested_at`, `sold_order_id`, `refund_amount`, `refund_status`, `refund_attempts`, `stripe_refund_id`, `closed_reason`, timestamps. A partial unique index allows one `LISTED` row per attendee.

- Stored listing statuses: `LISTED`, `SOLD`, `CLOSED`. `closed_reason` records why (withdrawn, cutoff passed, ticket cancelled, ticket type changed, event or date cancelled, closed by organizer).
- A **claim** is live when `claimed_by_order_id` points at an order that is not soft-deleted, is `RESERVED` and has `reserved_until` in the future. An expired or abandoned order's claim is ignored and may be overwritten. Nothing has to release claims.
- A reservation that holds a claim never runs past the resale cutoff: its `reserved_until` (and a waitlist offer's expiry) is capped at the cutoff when the claim is made. So no claim is live after the cutoff.
- A listing is **effective** when it is `LISTED`, the cutoff has not passed, and either it has a live claim or resale is still enabled, the event is `LIVE` and `withdrawal_requested_at` is null. An effective listing locks the seller's ticket at the door. After the cutoff the ticket works again by derivation, whether or not any job has run.
- **Withdrawal** of an unclaimed listing closes it at once. Withdrawal while a claim is live sets `withdrawal_requested_at`: no new order can claim it, the current buyer may still finish (the seller is then refunded as normal), and if that checkout lapses the listing is closed. A seller is therefore never locked out for longer than one reservation.
- An order holding a claim cannot switch to offline payment and cannot create a payment link (which would extend its reservation); the frontend hides both.
- A scheduled command closes listings past the cutoff or with a lapsed withdrawal request, emails the seller that the ticket did not sell, re-dispatches refunds for sold listings that have no refund after 15 minutes, and prunes expired verification rows. Door and stock behaviour do not depend on it.

*Alternative considered:* cancelling the seller's ticket at listing time and refunding when anyone buys. Rejected: the seller would lose the ticket with no guaranteed sale, and a refund could not be tied to a specific replacement payment.

### 7. Listings as stock: per price, primary first

Listing stock belongs to its exact price and occurrence and to nothing else. For a price and occurrence:

`available = min(primary at the price, each capacity pool, the occurrence) + unclaimed effective listings`

where every "reserved" figure used for the primary terms is net of live claims (a reserved unit backed by a claim occupies no primary stock). Listings are never added to a shared capacity pool or to occurrence capacity, because another ticket type drawing on that pool must not be able to sell against them. A listed ticket already occupies its pools, so swapping its holder is pool-neutral.

- The listing terms are opt-in on `AvailableProductQuantitiesFetchService` and are passed only by public checkout and the waitlist. Box office, manual attendee creation and admin edits never see resale stock. Flagged and unflagged results use separate cache keys; listing, withdrawal, claim and closure invalidate them.
- Every other reader of "sold out" for public sale must use the same figure: `OrderCreateRequestValidationService` (per-price and overall-capacity checks), `ProductPriceDomainObject::isSoldOut()` and its `quantity_sold >= initial` fallback, `AvailableProductQuantitiesResponseDTO`, `ProductFilterService`, the product and price resources, `OccurrencePurchaseEligibilityService` and the public events list.
- Sequential tier release ignores listings: a tier is exhausted when its primary stock is, so a listing on tier 1 never re-locks tier 2.

`ResaleClaimService`, called from the reservation path shared by `CreateOrderHandler` and waitlist offers, runs under the event lock:

1. For each order line, work out the units needed beyond primary availability at that price and occurrence.
2. For that shortfall only, claim the oldest claimable listings of the same price and occurrence. A listing is claimable when it is effective, unclaimed, has no withdrawal request, was paid on the organizer's current Stripe account, and its `min_unit_gross` is above zero and not above the line's per-ticket gross.
3. If the shortfall cannot be met, the order fails as sold out, exactly as today.

"Primary first" therefore needs no setting: a listing only fills demand that primary stock could not. Listing dispatches `CapacityChangedEvent(INCREASED)` so the waitlist's auto-offer picks it up.

### 8. Completing a resale

Completion runs inside the existing `PaymentIntentSucceededHandler` transaction after taking the event lock (the same lock claims are made under), with the buyer's order locked first and then seller orders in id order. For each listing still claimed by the completing order:

1. If the seller's attendee is still `ACTIVE`: cancel it through the same steps as an organizer cancel (quantity decrement, statistics, `ATTENDEE_CANCELLED`) but without `CapacityChangedEvent`, because the freed unit is consumed by the buyer in the same transaction. Mark the listing `SOLD` with `sold_order_id` and `refund_status = PENDING`. Net stock and "tickets sold" statistics are unchanged.
2. If the seller's attendee was already cancelled (the listing was closed while claimed), that cancellation freed a primary unit, so the buyer's unit is an ordinary primary sale and no resale refund is made.

After commit, `ProcessResaleRefundJob` and the `attendee.resold` event are dispatched per sold listing.

Guards on other paths:

- The expired-reservation path in `PaymentIntentSucceededHandler` re-runs the claim step when it re-checks stock. If stock (primary or a still-claimable listing) cannot cover the order, the buyer is refunded as today and the seller keeps the ticket.
- `CompleteOrderHandler` refuses to complete a zero-total order that holds a claim. Claims require a line gross above zero, so this is a defensive check.
- Listings close only when the seller's ticket is cancelled, its ticket type or price is changed by the organizer, or the seller or organizer closes it. A refund of the seller's order without cancellation does not close a listing; the refund cap in decision 9 covers it.
- An organizer cannot change the ticket type of a listing that is claimed, and cannot re-activate a ticket that was resold.
- Both check-in paths (`CreateAttendeeCheckInService` and `CheckInAttendeeHandler`) refuse a ticket with an effective listing.

The seller's ticket status is the existing `CANCELLED`; "Resold" is shown from the listing. No attendee status is added, since `AttendeeStatus` is part of the public API.

### 9. The seller's refund

`ProcessResaleRefundJob`, per listing, serialised by the seller-order lock:

1. If the listing already has a `stripe_refund_id`, stop.
2. If the seller's order has `REFUND_PENDING`, release and retry with backoff.
3. Read the replacement order's row in `order_payment_platform_fees`. If it is not there yet, retry; after a bounded wait, fetch the charge with its balance transaction through the existing extraction service.
4. Compute, in minor units of the order currency:
   - `basis = min(seller per-ticket gross, replacement per-ticket gross)`
   - `costs = card fee + application fee of the replacement order`
   - `share = max(costs × replacement per-ticket gross ÷ order gross, costs ÷ number of paid tickets in the order)`, rounded up
   - `refund = basis − share`, floored at zero and capped at the seller order's unrefunded balance.
5. Create the refund with idempotency key `resale_refund_{listing id}_{refund_attempts}` and the listing id in the refund's metadata, then store `stripe_refund_id` and `refund_amount` and email the seller the exact amount. A retry after a crash reuses the same key, so Stripe returns the same refund.
6. `ChargeRefundUpdatedHandler` matches the refund to the listing by metadata and sets `refund_status` to `SUCCEEDED` or `FAILED`. A failure emails the organizer (not a buyer mail) and flags the listing; a user with the refund permission can retry, which increments `refund_attempts`. The job's `failed()` handler also marks the refund failed, so a seller is never left voided and unflagged.

Taking the larger of the proportional and the per-ticket share covers the fixed per-ticket part of the application fee whatever the mix of prices in the replacement order, so the deduction is never less than the resale ticket's real incremental cost.

The amount shown when listing (`estimated_refund`) uses the application fee at the current price plus the per-ticket card fee actually charged on the seller's own order. It is labelled as approximate.

*Alternatives considered:* an organizer-set resale fee, a buyer surcharge, a flat percentage. Rejected for complexity or because they cannot guarantee the organizer is whole.

### 10. Resale eligibility

A ticket can be listed only when all of these hold:

- resale is on for the event and the ticket type; the event is `LIVE`; the ticket's price is `PAID` or `TIERED`, is currently on sale and is not seated;
- the attendee is `ACTIVE`, not checked in and never transferred; the date is in the future, not cancelled and before the resale cutoff;
- the order is completed and was paid online through Stripe (not box office, not offline, not through a payment link) on the organizer's current Stripe account;
- the fee recorded for the seller's order is in the order's currency (the account settles in the event currency);
- the order's only refunds are earlier resale refunds, and none has failed;
- the per-ticket amount paid, and the current per-ticket price, are above zero;
- there is no pending transfer and no existing listing.

Add-ons on the seller's order are untouched. The listing screen names them and says they are not included or refunded.

### 11. Settings

- `event_settings`: `allow_ticket_transfer`, `ticket_transfer_cutoff_minutes` (default 0), `max_transfers_per_ticket` (nullable, unlimited), `allow_ticket_resale` (default false), `ticket_resale_cutoff_minutes` (default 120).
- `organizer_settings`: `default_allow_ticket_transfer`, `default_allow_ticket_resale`.
- `products`: `is_transferable`, `is_resellable` (both default true).
- `allow_ticket_transfer` and its organizer default are backfilled from `allow_attendee_self_edit`, so an event that turned self-service off does not silently gain transfers. New organizers default to on.

### 12. Organizer visibility and data lifecycle

- An audit read endpoint returns `order_audit_logs` for an attendee or order with ticket links, QR values, IP addresses and user agents stripped; the attendee and order drawers show it as a history list. New `OrderAuditAction` cases cover transfer and resale steps.
- A resale page per event lists listings with status, replacement order, refund amount and refund state, with close (unclaimed listings) and retry-refund actions. Money fields sit behind `VIEW_FINANCIALS`; retry requires the refund permission.
- New `DomainEventType` cases feed webhooks; the webhook form list and the webhook table in `config/scramble.php` gain them.
- `ticket_transfers` and `ticket_resale_listings` are event-owned and hold emails: each gets an anonymizer and a place in `AccountHardDeletionService`'s delete order. `self_service_verifications` is email-scoped across accounts like `ticket_lookup_tokens`, is not account-owned, and is pruned once expired.

### 13. Decisions made during implementation

- **Listing minimum is a price before taxes and fees.** The listing stores `min_unit_price` (the lower of what the seller paid per ticket before additions and today's price for that tier and date). Stock counts compare it in SQL with the current price, including a per-date price override; a claim compares it with the order line's unit price after discounts. The refund formula still works on gross amounts.
- **Reserved figures are always net of live claims**, for every caller, because a reservation backed by a listing never uses unsold stock. The opt-in flag only adds unclaimed effective listings to the per-price figure.
- **No explicit cache invalidation.** The existing event-wide quantity cache is a two-second time-based cache with no invalidation; the resale-aware variant uses a separate key with the same lifetime.
- **Mixed orders.** An order line draws on listings only for the units its own price and date cannot supply from unsold stock. Pool and date-capacity checks then count only the units that need unsold stock.
- **Paid on the organizer's current Stripe account** is checked in SQL against the organizer's latest completed Stripe platform, so a listing paid on a replaced account adds no stock and cannot be claimed.
- **Expired reservations.** A paid order whose reservation has expired is refunded as today. The only orders rescued after expiry are seated orders whose items are all still available from unsold stock, so a rescued order completes as an ordinary sale and never completes a resale.
- **Completion takes the event lock**, and so do order cancellation and the organizer's attendee edit, so a seller ticket cannot be cancelled and resold at the same instant.
- **Check-in and listings are atomic.** Check-in locks the ticket's listing row: an effective listing refuses the check-in; a listing that is no longer effective (past the cutoff, resale turned off, event not live) is closed as `TICKET_USED` in the same transaction, so it can never become sellable again after the ticket was used.
- **Listings on events that stop offering resale** are closed by the scheduled command with reason `RESALE_TURNED_OFF` and the seller is told the ticket is theirs to use.
- **Statistics.** `quantity_sold`, pool and date usage and `attendees_registered` are unchanged by a resale. `products_sold` counts the replacement sale, as it already does when an organizer cancels a single ticket.
- **The QR value stays in the public payload of a listed ticket.** Removing it would change the type of an existing public field. The door refuses a listed ticket on the server, and the order and ticket pages show the listing state in place of the code.
- **Zero refunds.** If the calculated refund is zero (fees at or above the price, or the order has nothing left to refund) the listing is marked settled with a zero amount and no Stripe refund is created.

## Risks / Trade-offs

- **Chargeback on the replacement payment.** The seller is refunded as soon as the replacement payment succeeds. If the replacement buyer later disputes their charge and wins, the organizer has lost that seat's revenue plus Stripe's dispute fee, where without the resale they would have kept the seller's payment. This cannot be designed away short of holding the seller's refund for months. → Resale is off by default, the setting's description states this exposure, and the listing's replacement order is linked so a disputed order can be traced. This is the one case where the "never worse off" goal does not hold. The owner accepted this exposure on 2026-11-25.
- **Breaking the public email edits.** External callers changing attendee or order emails through the public self-service endpoints will get a validation error. → Called out in the proposal, PR description and release notes. The authenticated organizer endpoints are unaffected.
- **The seller's quote is approximate.** The replacement card can cost more than the estimate, and the per-ticket share rule can deduct a few cents more in a mixed order. → The screen says "about" and the exact amount is in the confirmation mail.
- **Refund held or failed.** If the organizer's available Stripe balance is short a card refund waits, and can fail after 45 days; other payment methods can fail outright. → The seller's ticket is already void, so failures are flagged to the organizer with a retry action and the seller is told to contact the organizer.
- **Disputed original charge.** Stripe refuses to refund a disputed charge, so the refund fails and is flagged. → Accepted; dispute handling is separate work.
- **Seller's order becomes partially refunded.** `RefundOccurrenceOrdersJob` skips orders that already have a refund, so if the date is later cancelled the seller's remaining tickets are not refunded automatically. → Existing behaviour for any partially refunded order; it writes an audit entry and the organizer refunds manually.
- **Seller's ticket is locked while listed.** → Stated on the listing screen; a withdrawal request guarantees release within one reservation, and the lock lifts at the cutoff.
- **Waitlist offers hold a listing for the offer timeout** (12 hours by default). → The seller sees when the hold ends; the hold is capped at the cutoff.
- **Price cuts strand listings.** If the organizer lowers the price below a listing's `min_unit_gross`, it cannot sell. → The seller is told at the cutoff and can relist.
- **Stock derivation is touched in many places.** → Listing stock is opt-in, so a missed caller fails safe (it does not see listings). Feature tests cover every sold-out surface, shared pools, occurrence capacity and tier release.
- **Stripe account changed.** Listings paid on a previous Stripe account are not claimable, since the refund would be drawn from an account that did not receive the replacement money.
- **Orphaned add-ons.** → Disclosed before listing; the organizer can refund them manually.
- **Fraud.** A stolen card cannot be cashed out, since refunds return to the same card.

## Migration Plan

1. Ship group by group (see `tasks.md`); each group is releasable on its own. Hardening and verification go first and carry the breaking change.
2. New columns have defaults or are nullable. The only backfill copies `allow_attendee_self_edit` into `allow_ticket_transfer` (and the organizer default likewise); `allow_ticket_resale` defaults off.
3. Rollback: both features are switched off per event by their settings. With resale off, unclaimed listings stop being effective at once and their tickets work again; closing a listing never moves money. Refunds already created are unaffected.

## Open Questions

- Wording and placement of the resale explanation in the help centre.
- Whether the resale page should later grow into a combined "transfers and resale" activity page.
