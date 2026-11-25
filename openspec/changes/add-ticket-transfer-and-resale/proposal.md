# Proposal

## Why

Attendees who can't make an event have no proper way to hand a ticket on or get their money back. The only tool today is editing an attendee's email from the order page, which leaves the previous holder's QR code working, needs nothing more than a forwarded order link, and gives organizers no record of what happened. Organizers selling out events lose the chance to fill returned seats, and buyers resort to unsafe off-platform swaps.

## What Changes

- **Ticket transfer.** A ticket holder sends a ticket to another person by email. The recipient accepts through a claim link; until they do, the sender keeps a working ticket and can cancel. On acceptance the ticket's QR code and link are replaced, the original buyer's order page stops showing it, and both sides are emailed.
- **Ticket resale (refund-on-resale).** A buyer lists a ticket they can't use. Once the same ticket type and date has no primary stock left, the listed ticket is sold through the normal checkout at the current price. Only after the replacement payment succeeds is the seller's ticket voided and the seller refunded to their original card from the organizer's Stripe account. The refund is what the seller paid (capped at the replacement price) minus the replacement sale's actual card and platform fees. The platform never holds money. The organizer is not out of pocket on the resale itself; the remaining exposure is a later chargeback on the replacement payment, which the resale setting discloses.
- **Verified self-service session.** Transfer, resale and changing a ticket's or an order's email require a six-digit code sent to the acting person's email on file. Arriving through the "look up my tickets" email link counts as verified.
- **Hardening of what exists today.**
  - Any change of a ticket's email (self-service or organizer) replaces the QR code as well as the link, emails the new address, notifies the old one and is logged.
  - Self-service attendee edits emit the same `attendee.updated` event as organizer edits.
  - "Look up my tickets" finds tickets by attendee email as well as buyer email.
  - Organizers can see a ticket's history (edits, transfers, resale) in the attendee and order views.
- **Organizer controls.** Per event: transfer on/off, transfer cutoff, maximum transfers per ticket, resale on/off, resale cutoff. Per ticket type: transferable and resellable switches. Organizer-level defaults for the two on/off switches. A resale view per event shows listings, sales and refund states, with failed refunds flagged.
- **New webhook events** `attendee.transferred`, `attendee.resale_listed` and `attendee.resold` (additive).
- **BREAKING (public self-service API):** `PATCH /public/events/{event_id}/order/{order_short_id}/attendees/{attendee_short_id}` and `PATCH /public/events/{event_id}/order/{order_short_id}` reject a change of `email` unless the request carries a verified self-service session. Name-only edits are unchanged. The order rule is needed because otherwise a forwarded order link could be used to point the order at a new address and receive the code there.
- **Behaviour change (public order payload):** a ticket that has been transferred to a different email address is no longer included in the `attendees` list of `GET /public/events/{event_id}/order/{order_short_id}`; it appears in a new `transferred_tickets` list with a masked recipient email and no QR value or ticket link.
- Both features ship in core with no licence gate.

Out of scope for this change: resale of reserved-seating tickets, resale of free, donation, offline-paid, box-office or complimentary tickets, resale where the organizer's Stripe account settles in a different currency from the event, resale of a ticket that was already transferred, a "release my ticket" action for free events, above-face-value pricing, buyer surcharges, and automatic refunds of add-ons bought alongside a resold ticket.

## Capabilities

### New Capabilities

- `self-service-verification`: proving control of an email address before sensitive self-service actions, and finding tickets by email.
- `ticket-transfer`: handing a ticket to another person, the claim flow, credential replacement, organizer controls and ticket history.
- `ticket-resale`: listing a ticket for resale, matching it to a replacement sale, voiding and refunding the seller, organizer controls and oversight.

### Modified Capabilities

None. `openspec/specs/` has no existing capabilities.

## Impact

- **Backend:** new tables for transfers, resale listings and self-service verifications; new columns on `attendees`, `event_settings`, `organizer_settings` and `products`; new public and organizer endpoints; changes to stock availability, order reservation, payment completion, the offline-payment transition, check-in validation, the refund webhook handler, attendee edit handlers, ticket lookup and the public order and attendee resources; new mails, audit actions, domain event types and a scheduled command.
- **Frontend:** order page, ticket page, "my tickets" page, a new transfer claim page, event and organizer settings, product form, attendee and order drawers, a new event resale page, webhook form; translations for every locale; new E2E specs.
- **Public API:** the breaking email-edit rule and the order payload change above; everything else is additive. The committed API reference must be re-exported.
- **Stripe:** no new products or capabilities. Refunds use the existing direct-charge refund path on the organizer's account.
- **Account deletion:** anonymizers and hard-delete ordering for the two new event-owned tables.
- **Docs:** a new `.claude/rules/features/ticket-transfer-resale.md` and a row in the `AGENTS.md` table.
- **Not covered:** the unmerged `feature/wallet-passes` branch. When it lands, replacing a ticket's QR code must also revoke its pass.
