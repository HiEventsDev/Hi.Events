# Spec Delta

## Purpose

Lets a buyer who can't attend put their ticket back on sale and be refunded once someone else buys it, without the platform holding money and without the organizer ever being worse off than if the resale had not happened.

## ADDED Requirements

### Requirement: List a ticket for resale
The system SHALL let the verified buyer of an order list one of its tickets for resale after showing the approximate amount they will get back. A listed ticket SHALL remain the seller's until it is sold.

#### Scenario: Buyer lists a ticket
- **WHEN** a verified buyer confirms the listing screen for an eligible ticket
- **THEN** the ticket shows as "listed for resale" on the order and ticket pages
- **AND** the seller receives an email confirming the listing

#### Scenario: Estimate shown before listing
- **WHEN** the listing screen is opened
- **THEN** it shows what the seller paid, the estimated card and platform fees of the replacement sale, and the approximate refund, marked as approximate

#### Scenario: Add-ons disclosed
- **WHEN** the seller's order contains add-ons
- **THEN** the listing screen names them and states they are not included in the resale or refunded

### Requirement: Ticket eligibility for resale
The system SHALL only allow a listing when resale is enabled for the event and ticket type, the event is live, the ticket is a fixed-price or tiered ticket currently on sale, active, unseated, not checked in and never transferred, the date is before the resale cutoff and not cancelled, and the ticket has no pending transfer or listing. Only the order's buyer SHALL be able to list.

#### Scenario: Transferred ticket
- **WHEN** a listing is requested for a ticket that was transferred to its current holder
- **THEN** the request is refused, because the refund could only go to the original payer

#### Scenario: Donation ticket
- **WHEN** a listing is requested for a pay-what-you-want ticket
- **THEN** the request is refused

#### Scenario: Ticket type no longer on sale
- **WHEN** sales for the ticket type have ended
- **THEN** the request is refused, because the ticket could not be bought by anyone

#### Scenario: Seated ticket
- **WHEN** a listing is requested for a reserved-seating ticket
- **THEN** the request is refused

#### Scenario: Requested by a guest
- **WHEN** a person verified as a ticket's attendee email, but not the order's email, requests a listing
- **THEN** the request is refused

### Requirement: Payment eligibility for resale
The system SHALL only allow a listing when the order was paid online through Stripe on the organizer's current account, the account settles in the event's currency, the order's only refunds are earlier resales, and the price paid for the ticket is above zero.

#### Scenario: Free ticket
- **WHEN** a listing is requested for a ticket that cost nothing
- **THEN** the request is refused and no resale action is offered

#### Scenario: Offline or box-office order
- **WHEN** a listing is requested for a ticket on an order paid offline or at the box office
- **THEN** the request is refused

#### Scenario: Order already refunded by the organizer
- **WHEN** a listing is requested for a ticket on an order the organizer has partly refunded
- **THEN** the request is refused

#### Scenario: Second ticket from the same order
- **WHEN** one ticket on an order was resold and refunded and the buyer lists another ticket from that order
- **THEN** the listing is accepted

#### Scenario: Organizer changed Stripe account
- **WHEN** the order was paid on a Stripe account the organizer has since replaced
- **THEN** the request is refused

### Requirement: Listed tickets cannot be used
The system SHALL refuse check-in, by scan or by an organizer's manual check-in, for a ticket while its listing is open, and SHALL show the listing state in place of the QR code on the ticket.

#### Scenario: Scanning a listed ticket
- **WHEN** a listed ticket's QR code is scanned
- **THEN** check-in is refused with a message that the ticket is listed for resale

#### Scenario: Manual check-in of a listed ticket
- **WHEN** an organizer checks in a listed ticket from the attendee list
- **THEN** the check-in is refused with the same message

#### Scenario: Ticket page while listed
- **WHEN** the ticket page is opened while the ticket is listed
- **THEN** the QR code is replaced by the listing state
- **AND** withdrawing is offered on the buyer's order page, since the ticket link may belong to a guest

### Requirement: Withdraw a listing
The system SHALL let the seller withdraw a listing at any time. If a buyer's unexpired checkout holds the ticket, the withdrawal SHALL take effect when that checkout lapses, and no other buyer SHALL be able to take the ticket in the meantime.

#### Scenario: Seller withdraws
- **WHEN** the seller withdraws a listing no buyer is holding
- **THEN** the ticket works again immediately

#### Scenario: Buyer is checking out and abandons
- **WHEN** the seller withdraws while a buyer's checkout holds the ticket, and that checkout then expires
- **THEN** the listing is closed and the ticket works again
- **AND** the seller was shown the time by which the hold ends

#### Scenario: Buyer is checking out and pays
- **WHEN** the seller withdraws while a buyer's checkout holds the ticket, and that buyer then pays
- **THEN** the resale completes and the seller is refunded

#### Scenario: Repeated checkouts cannot hold a ticket
- **WHEN** a withdrawal has been requested and a second buyer starts a checkout
- **THEN** the second buyer cannot take the listed ticket

### Requirement: Primary stock sells first
The system SHALL sell a listed ticket only to fill demand that the event's own unsold stock for the same ticket type, price tier and date cannot meet.

#### Scenario: Primary stock remains
- **WHEN** 3 tickets of a type are unsold, 1 is listed for resale, and a buyer orders 2
- **THEN** both come from unsold stock and the listing stays open

#### Scenario: Sold out with a listing
- **WHEN** a ticket type has no unsold stock and 1 open listing
- **THEN** the ticket type shows as available with 1 remaining
- **AND** a buyer ordering 1 holds the listed ticket during checkout

#### Scenario: Mixed order
- **WHEN** 1 ticket is unsold, 2 are listed, and a buyer orders 3
- **THEN** the order holds the unsold ticket and both listings

#### Scenario: Another ticket type sharing capacity
- **WHEN** two ticket types share a full capacity pool and a ticket of one type is listed
- **THEN** availability rises by 1 for that type only
- **AND** an order for the other type is refused as sold out

#### Scenario: Tiered release
- **WHEN** tier 1 has sold out, tier 2 is on sale, and a tier 1 ticket is listed
- **THEN** tier 2 stays on sale and the tier 1 listing is purchasable

#### Scenario: Different date
- **WHEN** a ticket for one date of a recurring event is listed
- **THEN** it adds availability only to that date

### Requirement: Resale stock is online checkout only
The system SHALL offer listed tickets only through public checkout and waitlist offers, and SHALL require an order holding a listed ticket to be paid online by the buyer in that checkout.

#### Scenario: Box office
- **WHEN** a ticket type is sold out apart from an open listing
- **THEN** the box office shows it as sold out

#### Scenario: Offline payment
- **WHEN** a buyer whose order holds a listed ticket reaches the payment step
- **THEN** offline payment is not offered and a request to use it is refused

#### Scenario: Payment link
- **WHEN** a buyer whose order holds a listed ticket asks for a link so someone else can pay
- **THEN** the request is refused

#### Scenario: Manual attendee creation
- **WHEN** an organizer adds an attendee to a ticket type that is sold out apart from an open listing
- **THEN** the listing is not used

### Requirement: Waitlist receives listed tickets
The system SHALL treat a new listing as freed capacity for the waitlist, so that an event with automatic offers makes an offer to the next waiting person.

#### Scenario: Auto-offer on listing
- **WHEN** a ticket is listed for a sold-out type that has people waiting and automatic offers enabled
- **THEN** the next person is offered the ticket and their offer holds the listing

### Requirement: Seller is protected from low-priced sales
The system SHALL NOT sell a listed ticket to an order line whose per-ticket price is zero or below the price the seller's estimate was based on.

#### Scenario: Discounted order
- **WHEN** a ticket type is sold out apart from a listing, and a buyer applies a promo code that lowers the price below the listing's basis
- **THEN** the order is refused as sold out and the listing stays open

#### Scenario: Price lowered after listing
- **WHEN** the organizer lowers the ticket price below a listing's basis
- **THEN** that listing no longer adds availability

### Requirement: Holds end at the resale cutoff
The system SHALL NOT let a checkout or waitlist offer hold a listed ticket beyond the resale cutoff.

#### Scenario: Waitlist offer near the cutoff
- **WHEN** a waitlist offer with a 12 hour timeout takes a listed ticket 3 hours before the resale cutoff
- **THEN** the offer expires at the cutoff

#### Scenario: Payment after the cutoff
- **WHEN** a buyer holding a listed ticket pays after the cutoff has passed
- **THEN** the buyer is refunded and the seller keeps the ticket

### Requirement: Released checkouts free the listing
The system SHALL make a listed ticket available again when the checkout holding it is abandoned or expires, without any action by the seller.

#### Scenario: Buyer abandons
- **WHEN** a buyer's checkout holding a listed ticket expires
- **THEN** the ticket is available to the next buyer and the seller's listing is still open

### Requirement: Resale completes on payment
The system SHALL, when the replacement order's payment succeeds, void the seller's ticket, issue the buyer's ticket and mark the listing sold as one atomic step. The number of tickets counted as sold SHALL be unchanged.

#### Scenario: Successful resale
- **WHEN** the replacement buyer's card payment succeeds
- **THEN** the seller's ticket is cancelled and its QR code no longer works
- **AND** the buyer receives a new ticket as for any purchase
- **AND** the ticket type's sold count is the same as before the resale

#### Scenario: Payment arrives after the checkout expired
- **WHEN** the buyer's payment succeeds after their reservation expired
- **THEN** the buyer is refunded as today and the seller keeps their ticket and listing

#### Scenario: Organizer cancelled the seller's ticket meanwhile
- **WHEN** the organizer cancels the seller's ticket while a buyer's checkout holds it, and the buyer then pays
- **THEN** the buyer's order completes as a normal sale and no resale refund is made

#### Scenario: Organizer refunded the seller without cancelling
- **WHEN** the organizer partly refunds the seller's order without cancelling the ticket while a buyer's checkout holds it, and the buyer then pays
- **THEN** the seller's ticket is cancelled, only one valid ticket exists for the place, and the seller's refund is limited to what their order has left to refund

#### Scenario: Resold ticket cannot be re-activated
- **WHEN** an organizer tries to re-activate a ticket that was resold
- **THEN** the request is refused

### Requirement: Seller refund amount
The system SHALL refund the seller the lower of what they paid for the ticket and what the replacement buyer paid for it, minus that ticket's share of the replacement order's actual card processing and platform fees. The share SHALL be the larger of the share by price and an equal split across the order's paid tickets. The refund SHALL never be below zero or above what the seller's order has left to refund.

#### Scenario: Same price
- **WHEN** the seller paid 50.00, the replacement buyer pays 50.00, and the replacement's card fee is 1.00 and platform fee 1.50
- **THEN** the seller is refunded 47.50

#### Scenario: Price has risen
- **WHEN** the seller paid 50.00 and the replacement buyer pays 60.00 with fees of 3.00
- **THEN** the seller is refunded 47.00

#### Scenario: Several tickets in the replacement order
- **WHEN** the replacement order contains 4 equally priced tickets with total fees of 8.00 and one of them is a resold ticket
- **THEN** 2.00 of fees is deducted from that seller's refund

#### Scenario: Cheap resold ticket in a mixed order
- **WHEN** the replacement order contains a resold ticket at 10.00 and another ticket at 190.00, with total fees of 6.00
- **THEN** 3.00 of fees (the equal split) is deducted, not 0.30 (the share by price)

### Requirement: Organizer is never worse off
The system SHALL issue the seller's refund only after the replacement payment has succeeded, and the organizer's net proceeds from the two orders after the refund SHALL be at least their net proceeds from the original sale.

#### Scenario: No sale, no refund
- **WHEN** a listed ticket never sells
- **THEN** no refund is made and the seller keeps the ticket

#### Scenario: Net position
- **WHEN** a resale completes and the seller's refund is paid
- **THEN** the organizer's Stripe balance from the two orders together is not lower than it was from the original order alone

### Requirement: Refund goes to the original payment method
The system SHALL pay the seller's refund from the organizer's payment account to the card used for the original order, and the platform SHALL NOT receive or hold any part of it.

#### Scenario: Refund destination
- **WHEN** the seller's refund is created
- **THEN** it is a partial refund of the seller's original card payment on the organizer's Stripe account

### Requirement: Refund reliability
The system SHALL process resale refunds one at a time per order, retry when another refund on the same order is in flight, never refund the same resale twice, and report a refund that failed or could not be started to the organizer with a way to retry it.

#### Scenario: Two tickets from one order resold together
- **WHEN** two tickets from the same seller order are resold within seconds of each other
- **THEN** both refunds are made, one after the other, each for its own amount

#### Scenario: Processing interrupted after the refund was created
- **WHEN** refund processing stops after Stripe created the refund but before it was recorded, and processing runs again
- **THEN** no second refund is created and the original one is recorded

#### Scenario: Refund never started
- **WHEN** a resale completed but its refund has not been created after 15 minutes
- **THEN** refund processing is started again automatically

#### Scenario: Refund fails
- **WHEN** Stripe reports the seller's refund as failed
- **THEN** the organizer is emailed, the resale is flagged as needing attention, and the seller is told to contact the organizer

#### Scenario: Organizer retries
- **WHEN** a team member with permission to issue refunds retries a failed resale refund
- **THEN** a new refund is attempted for the same amount

### Requirement: Seller notifications
The system SHALL email the seller when the ticket is listed, when it sells with the exact refund amount, and when a listing closes unsold.

#### Scenario: Sold
- **WHEN** the seller's refund is created
- **THEN** the seller is emailed the exact amount and that it is on its way to their card

#### Scenario: Unsold at cutoff
- **WHEN** the resale cutoff passes with the ticket unsold
- **THEN** the seller is emailed that the ticket is theirs to use, with a link to it

### Requirement: Listings close when the ticket can no longer be resold
The system SHALL close an open listing when the resale cutoff passes, the ticket or its order is cancelled, the organizer changes its ticket type, the organizer closes it, or the event or date is cancelled. After the cutoff an unsold ticket SHALL be usable again without any action.

#### Scenario: Cutoff passes
- **WHEN** the resale cutoff is 2 hours and the date starts in 1 hour with the ticket unsold
- **THEN** the ticket's QR code works at the door

#### Scenario: Organizer changes the ticket type
- **WHEN** an organizer moves a listed ticket that no buyer is holding to another ticket type
- **THEN** the listing is closed and the seller is told

#### Scenario: Ticket type change while held
- **WHEN** an organizer tries to change the ticket type of a listed ticket a buyer is holding
- **THEN** the change is refused

#### Scenario: Organizer closes a listing
- **WHEN** an organizer closes a listing no buyer is holding from the resale page
- **THEN** the ticket works again and the seller is told

#### Scenario: Date cancelled
- **WHEN** the organizer cancels the date of a listed ticket
- **THEN** the listing is closed and the ticket follows the normal cancellation and refund process

### Requirement: Organizer resale controls
The system SHALL let organizers turn resale on or off per event, set a resale cutoff before the start time, and mark individual ticket types as not resellable. Resale SHALL be off by default, organizers SHALL be able to set the default for new events, and the setting SHALL state that a chargeback on a replacement payment is at the organizer's risk.

#### Scenario: Resale off by default
- **WHEN** an event is created with untouched settings
- **THEN** no resale action is offered to its buyers

#### Scenario: Turning resale off with open listings
- **WHEN** the organizer turns resale off while listings are open
- **THEN** no new listings can be made, listings no buyer is holding no longer add availability, and those tickets work again

### Requirement: Organizer resale oversight
The system SHALL show organizers every listing for an event with its state, the replacement order when sold, and the refund amount and state, and SHALL hide money amounts from team members who cannot view financials.

#### Scenario: Resale page
- **WHEN** an organizer opens the event's resale page
- **THEN** they see open, sold and closed listings, with failed refunds marked as needing attention

#### Scenario: Member without financial access
- **WHEN** a team member without financial access opens the resale page
- **THEN** listings are shown without refund amounts

#### Scenario: Resold attendee
- **WHEN** an organizer views the seller's cancelled attendee
- **THEN** it is shown as resold, with a link to the replacement order

### Requirement: Resale events for integrations
The system SHALL emit `attendee.resale_listed` when a ticket is listed and `attendee.resold` when a resale completes.

#### Scenario: Webhook on resale
- **WHEN** a resale completes and a webhook subscribes to `attendee.resold`
- **THEN** the webhook receives the seller's attendee and the replacement order reference
