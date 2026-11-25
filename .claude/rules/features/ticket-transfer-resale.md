---
paths:
  - "backend/app/**/TicketTransfer/**"
  - "backend/app/**/*TicketTransfer*.php"
  - "backend/app/**/SelfService/**"
  - "backend/app/Services/Domain/Attendee/AttendeeCredentialRotationService.php"
  - "frontend/src/components/common/TicketTransfer/**"
  - "frontend/src/components/common/VerifyEmailModal/**"
  - "frontend/src/components/routes/ticket-transfer/**"
  - "frontend/src/hooks/useTicketTransferActions.tsx"
  - "frontend/src/hooks/useSelfServiceVerification.tsx"
  - "frontend/src/utilites/selfServiceSession.ts"
  - "e2e/**/ticket-transfer*"
  - "e2e/**/email-verification*"
  - "backend/app/**/TicketResale/**"
  - "backend/app/**/*TicketResale*.php"
  - "backend/app/**/*Resale*.php"
  - "backend/app/Services/Domain/Product/AvailableProductQuantitiesFetchService.php"
  - "backend/app/Services/Domain/Product/SoldAndReservedQuantitiesService.php"
  - "frontend/src/components/common/TicketResale/**"
  - "frontend/src/components/routes/event/Resale/**"
  - "e2e/tests/resale/**"
---

# Ticket transfer, resale and verified self-service

## Who controls a ticket

- `TicketControlService` is the only rule: a verified email controls a ticket when it is the attendee's email, or the order's email and `attendees.transferred_at` is null. Once a transfer is accepted the buyer's order link no longer controls that ticket.
- An order link or ticket link alone is not enough for anything that moves a ticket or changes an email address. Those handlers call `VerifiedTicketAccessService` with the `X-Self-Service-Token` header. Name-only edits stay open.
- Changing the **order** email needs a session too. Without it, a forwarded order link could be pointed at a new address and the code sent there.
- A session comes from the six-digit code (`SelfServiceVerificationService`: hashed code, 10 minutes, 5 attempts, 30-minute session) or from a ticket lookup token. The code request never takes an email from the caller: it goes to the address on file for the link.
- The code mail carries no event content and no name, so nothing in it can be withheld for an unpublished event.

## Changing who holds a ticket

- Any change of an attendee's email (buyer edit, organizer edit, accepted transfer) replaces both `short_id` and `public_id` through `AttendeeCredentialRotationService`. The QR code is `public_id`, so the old one fails at the door at once. Wallet pass revocation, when it exists, hooks in here.
- Audit entries record `credentials_replaced`, never the id values. The history endpoint (`TicketHistoryEntryResource`) returns a whitelist of value keys and drops ticket links, QR values, IP addresses and user agents, including from rows written before this rule.
- An accepted transfer hard-deletes the previous holder's question answers (`forceDeleteForAttendee`): `question_and_answer_views` does not filter soft-deleted rows, so a soft delete leaves the old answers visible to organizers.

## Transfers

- Statuses stored on `ticket_transfers`: `PENDING`, `ACCEPTED`, `CANCELLED`, `DECLINED`, `EXPIRED`. Expiry is derived on read (`PENDING` and past `expires_at`). Nothing sweeps: a stale `PENDING` row is only marked `EXPIRED` when a new transfer is started, which frees the one-pending-per-attendee index.
- Any change of the ticket's email (organizer or buyer) cancels its pending transfer and tells the recipient (`TicketTransferInitiationService::cancelForHolderChange`), so an old invitation can never move a ticket away from its new holder.
- The sender's ticket keeps working while a transfer is pending. Acceptance re-checks `TicketTransferEligibilityService::blockOnHolderChange` under a transfer row lock and an attendee row lock.
- Eligibility lives in `TicketTransferEligibilityService` only. The frontend does not mirror it: the public attendee payload carries `transfer.can_start`, `blocked_reason` and `pending`, set by `TicketTransferStateService` in the order and ticket loaders.
- A ticket transferred to a different address leaves the public order payload's `attendees` and appears in `transferred_tickets` (masked email, no ids). Order-link edit, resend and seat change treat it as not found.
- The invitation goes to an unverified address: no sender-typed text, no greeting by name, sender shown masked. Starts are limited to 5 per ticket per day in the eligibility service, plus the `self-service-transfer` throttle (per link, per IP, per recipient).
- The claim request reuses checkout's `ProductQuestionRule`, so required per-attendee questions and disqualifying answers behave as they do at checkout. Order-level answers are not available to follow-up conditions there.
- A transfer needs the event to be `LIVE`. Tests that create questions through the API trip the spam check, which moves the event to `PENDING_MANUAL_REVIEW`; set it back before asserting transfers.

## Frontend

- `useSelfServiceVerification(link)` opens `VerifyEmailModal` only when there is no stored session; sessions live in `sessionStorage` per link, with the lookup session as a fallback. On `VERIFICATION_REQUIRED` (or a 422 on `email` for the edit endpoints) clear the session and verify again.
- New public pages must be added to `ALLOWED_UNAUTHENTICATED_PATHS` in `api/authErrorRedirect.ts`, or a signed-out visitor is sent to the organizer login.
- Buyer resale lives on the order page only: `useTicketResaleActions(orderLink, timezone)` verifies first, then opens `SellTicketModal` with the estimate. A listed ticket shows `TicketResaleListed` and none of the ticket link, print, transfer or edit actions. The ticket page never offers sell or withdraw, because the link may belong to a guest.
- `AttendeeTicket` replaces the QR code when `resale.status` is `LISTED` or `SOLD`, which covers the ticket page and both print views. Never render the QR code for a listed ticket anywhere else.
- E2E: `e2e/utils/resale.ts` (`markOrderPaidThroughStripe`) turns an offline-paid order into one paid through Stripe, which is all a listing needs; no real Stripe.
- Organizer oversight is `/manage/event/:id/resale` (`routes/event/Resale`); the failed-refund email links to that exact path. The status filter is server-side (`filter_fields[status]`); "Needs attention" is a client-side filter over the Sold page because the API cannot filter it. The Refund column shows only when the payload carries the money fields, "Close listing" needs `EDIT` and "Retry refund" needs `ISSUE_REFUNDS` as well. The organizer attendee payload's `resale` is `{status, sold_order_id, sold_order_public_id}`: a resold attendee shows "Resold" with the replacement order and no Activate action.
- E2E for organizer pages: `e2e/utils/resale-listings.ts` (`seedResaleListing`) inserts listings in any stored state. A retried refund stays `PENDING` when the replacement order has no platform-fee row, so settle it (`settleResaleRefund`) when the test ends.

## Resale

Resale is refund-on-resale: the buyer lists a ticket, it stays theirs until someone else pays for it, and the seller is then refunded from the organizer's Stripe account. The platform never holds money. Off by default per event.

### What a listing is

- Stored statuses on `ticket_resale_listings`: `LISTED`, `SOLD`, `CLOSED`. Everything else is derived in `TicketResaleListingRepository`; never store it and never add a job that door or stock behaviour depends on.
- A **claim** is live when `claimed_by_order_id` points at an order that is not deleted, is `RESERVED` and has `reserved_until` in the future. Nothing releases a claim: an abandoned or expired order's claim is ignored and overwritten.
- A listing is **effective** (locks the ticket at the door) when it is `LISTED`, before the cutoff on a date that is not cancelled, and either has a live claim or is open to new buyers (resale on, event `LIVE`, ticket type still resellable, no withdrawal request).
- A listing is **available** (adds stock, can be claimed) when it is effective, unclaimed, open to new buyers, paid on the organizer's current Stripe account and has `min_unit_price` above zero.
- Only the order's email may list or withdraw (`VerifiedTicketAccessService::assertControlsOrder`), and never a transferred ticket: the refund can only reach the original payer's card. Eligibility is `TicketResaleEligibilityService` only; the public payload carries `resale.can_list` and the frontend does not re-derive it.
- While a ticket is `LISTED`, its email cannot be changed (organizer edit: 422 on `email`; order page: 409) and the organizer cannot move it to any status but `CANCELLED`. Close or withdraw the listing first.
- When the attendee's email differs from the order's, the holder is emailed too, without amounts: when the ticket is listed (`LISTED_FOR_HOLDER`), resold (`SOLD_FOR_HOLDER`, from completion) and when it works again after a withdrawal or an unsold close (`RETURNED_FOR_HOLDER`).
- Withdrawing an unheld listing closes it. Withdrawing a held one sets `withdrawal_requested_at`: no new buyer can take it, the current buyer may still pay, and the scheduled command closes it once the hold lapses.

### Stock

- A listing belongs to its exact price and date. It is added to the per-price figure only, after the pool and date-capacity minimums, and never to a shared capacity pool or a date's capacity: another ticket type drawing on that pool must stay sold out.
- Reserved figures are net of live claims for every caller (`SoldAndReservedQuantitiesService`), because a reservation backed by a listing uses no unsold stock.
- Resale stock is opt-in (`includeResaleStock`) and passed only by public checkout, public product display and the waitlist. Box office, manual attendee creation, attendee edits and the organizer's availability views never see it.
- Unsold tickets sell first. `resaleUnitsNeeded` is the part of a line that unsold stock at that price and date cannot supply; pool and date checks count only the remaining units. `ResaleClaimService` claims that many of the oldest available listings under the event lock, and refuses the order as sold out if it cannot.
- A listing is never sold to a line priced below its `min_unit_price` (unit price before taxes and fees, after discounts), so discounted and free lines cannot take one.
- A reservation holding a claim is capped at the resale cutoff, cannot switch to offline payment and cannot create a payment link. If a claimed listing is past its cutoff by the time the payment lands (the organizer moved the cutoff or the date) or was closed for a reason that freed no ticket (used, withdrawn, closed by the organizer), the payment is refunded (`hasUnsellableClaim`) rather than completing as an ordinary sale.
- Sequential tier release looks at unsold stock only: a listing on a sold-out tier never re-locks the next tier.

### Completion and refund

- Completion runs inside the `PaymentIntentSucceededHandler` transaction under the event lock (`ResaleCompletionService`): the seller's attendee is cancelled with the quantity and statistics decrements of an organizer cancel but no `CapacityChangedEvent`, and the listing becomes `SOLD` with `refund_status` `PENDING`. Sold counts are unchanged.
- If the seller's ticket was already cancelled, that cancellation freed an unsold unit, so the buyer's order is an ordinary sale and no refund is made. An order whose reservation expired never completes a resale. `PaymentIntentSucceededHandler` takes the event lock (`lockEventIfHoldingListings`) before it checks expiry, so no other checkout can take the claim between that check and completion; completion itself never re-reads the clock.
- `ProcessResaleRefundJob` → `ResaleRefundService`, one at a time per seller order (order lock, retry while the order is `REFUND_PENDING`): refund = min(seller unit gross, replacement unit gross) − the larger of the proportional and the per-ticket share of the replacement order's card and application fees (`ResaleRefundCalculator`), floored at zero and capped at the seller order's unrefunded balance.
- The Stripe refund uses idempotency key `resale_refund_{seller payment intent}_{listing id}_{refund_attempts}`: Stripe keeps keys for 24 hours per account, and listing ids repeat across databases that share one Stripe account (staging and production, a restored database, CI runs), so the key must carry something Stripe itself made unique. It carries the listing id in its metadata. `ChargeRefundUpdatedHandler` matches on that metadata to settle or fail the listing. A retry by the organizer increments `refund_attempts`.
- A failed or never-started refund marks the listing `FAILED` and emails the organizer and the seller, including a refund Stripe reports as failed after first reporting success. Never leave a sold listing unflagged. A refund that works out to zero still tells the seller the ticket sold (`SOLD_WITHOUT_REFUND_FOR_SELLER`).
- Fees in the refund and the estimate are Stripe's total for the charge, including the VAT Stripe adds to its own fee on EU accounts (`OrderPaymentPlatformFeeDomainObject::getTotalFeeMinorUnit`/`getCardFeeMinorUnit`); `payment_platform_fee_amount` alone leaves the organizer out of pocket.
- The refund starts as soon as the new sale's fee is recorded (`ChargeSucceededHandler` → `startRefundsForSoldOrder`), and a refund waiting behind another on the same seller order starts once Stripe reports that one's outcome (`recordStripeOutcome`, dispatched after commit), so it works on a `sync` queue where released jobs never run again. Do not make `ProcessResaleRefundJob` `ShouldBeUnique`: a released unique job keeps its lock, which blocks every later dispatch on a sync queue.
- A not-ready attempt touches the listing, and `ticket-resale:maintain` only restarts refunds untouched for longer than the longest retry gap, so a live retry chain is never doubled. When the job gives up it calls `markFailedIfNotStarted`: once `stripe_refund_id` is set, only Stripe's answer settles or fails the listing. The seller's sold email is sent inside the refund lock, so overlapping jobs send it once.
- A started refund that Stripe never reports back on is fetched by `ticket-resale:maintain` after an hour (`ReconcileResaleRefundJob`) and run through `ChargeRefundUpdatedHandler`, so every ledger updates exactly as it would from the webhook.
- A chargeback on the replacement payment is the organizer's loss. The event setting says so; do not remove that sentence.

### Closing listings

- Every path that cancels a ticket or moves it to another ticket type closes its listing through `TicketResaleClosureService` (order cancel, attendee cancel, date cancel, organizer change of type, organizer close). A change of ticket type is refused while a buyer holds the listing. A resold ticket cannot be re-activated.
- Order cancellation, date cancellation (single and bulk, before the date's row lock), the organizer's attendee edit and resale completion all take the event lock, so a ticket cannot be cancelled and resold at once.
- Check-in (both `CreateAttendeeCheckInService` and `CheckInAttendeeHandler`) calls `lockForCheckIn` (listing row, then attendee row, the same order as completion) and re-checks the ticket's status under those locks; a status read before the transaction is never enough. Then an effective listing refuses the check-in, and a listing that is no longer effective is closed as `TICKET_USED` in the same transaction.
- `ticket-resale:maintain` closes listings that are past the cutoff, withdrawn or no longer offered, re-dispatches refunds that never started, asks Stripe about started refunds it never heard back on, and prunes expired verification rows. Stock and door behaviour must stay correct if it never runs.
