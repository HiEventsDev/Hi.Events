# Spec Delta

## Purpose

Lets a ticket holder hand a ticket to another person safely, so the new holder has the only working copy, and gives organizers control over and a record of every change of hands.

## ADDED Requirements

### Requirement: Start a transfer
The system SHALL let a person who controls a ticket send it to another email address. Starting a transfer SHALL email the recipient a claim link and leave the sender's ticket working until the recipient accepts.

#### Scenario: Buyer transfers from the order page
- **WHEN** a verified buyer chooses to transfer a ticket and enters a recipient email
- **THEN** the recipient is emailed a claim link
- **AND** the ticket shows as "transfer pending" with the recipient's email
- **AND** the ticket's QR code still works

#### Scenario: Holder transfers from the ticket page
- **WHEN** a verified ticket holder chooses to transfer from their ticket page
- **THEN** the same transfer is started

#### Scenario: Transfer to own address
- **WHEN** the recipient email is the same as the ticket's current email
- **THEN** the transfer is refused with a validation error

### Requirement: Transfer eligibility
The system SHALL only allow a transfer when transfers are enabled for the event and the ticket type, the event is live, the order is completed, the ticket is active and not checked in, the date is in the future and not cancelled, the transfer cutoff has not passed, the maximum number of transfers has not been reached, and the ticket has no pending transfer and no resale listing.

#### Scenario: Transfers disabled
- **WHEN** the organizer has turned transfers off for the event
- **THEN** no transfer action is offered and a transfer request is refused

#### Scenario: Ticket already checked in
- **WHEN** a transfer is requested for a checked-in ticket
- **THEN** the request is refused

#### Scenario: Past the cutoff
- **WHEN** the event's transfer cutoff is 60 minutes and the date starts in 30 minutes
- **THEN** the request is refused with a message that transfers have closed

#### Scenario: Transfer limit reached
- **WHEN** the event allows one transfer per ticket and the ticket has already been transferred once
- **THEN** the request is refused

#### Scenario: Too many attempts on one ticket
- **WHEN** a sixth transfer is started for the same ticket within 24 hours
- **THEN** the request is refused

#### Scenario: Ticket is listed for resale
- **WHEN** a transfer is requested for a ticket that is listed for resale
- **THEN** the request is refused until the listing is withdrawn

### Requirement: Cancel a pending transfer
The system SHALL let the sender cancel a transfer at any time before the recipient accepts, after which the claim link no longer works.

#### Scenario: Sender cancels
- **WHEN** the sender cancels a pending transfer
- **THEN** the ticket returns to its normal state
- **AND** opening the claim link shows that the transfer is no longer available

### Requirement: Transfer expiry
The system SHALL stop a pending transfer from being accepted after the earlier of 72 hours from when it was started and the event's transfer cutoff.

#### Scenario: Recipient is too late
- **WHEN** the recipient opens the claim link after the transfer has expired
- **THEN** they are told it has expired and the sender still holds the ticket

### Requirement: Accept a transfer
The system SHALL let the recipient accept through the claim link by giving their name and answering any required per-attendee questions. The recipient SHALL also be able to decline.

#### Scenario: Recipient accepts
- **WHEN** the recipient submits their name on the claim page
- **THEN** the ticket is issued to them and emailed to their address
- **AND** the sender is emailed that the transfer is complete

#### Scenario: Required questions
- **WHEN** the ticket type has required per-attendee questions
- **THEN** the claim page asks them and acceptance is refused until they are answered
- **AND** the previous holder's answers are replaced

#### Scenario: Recipient declines
- **WHEN** the recipient declines on the claim page
- **THEN** the sender keeps the ticket and is emailed that it was declined

#### Scenario: Ticket changed while pending
- **WHEN** the ticket was checked in or cancelled after the transfer was started
- **THEN** acceptance is refused and the recipient is told the ticket is no longer available

#### Scenario: Invalid name
- **WHEN** the recipient enters a name containing a link or other disallowed content
- **THEN** acceptance is refused with a validation error on the name

### Requirement: Old credentials stop working
The system SHALL replace a ticket's QR code value and ticket link whenever the ticket is accepted by a new holder or its email address is changed by the buyer or the organizer.

#### Scenario: Old QR code at the door
- **WHEN** the previous holder's QR code is scanned after a transfer was accepted
- **THEN** check-in fails as an unknown ticket

#### Scenario: Old ticket link
- **WHEN** the previous holder opens their old ticket link after a transfer was accepted
- **THEN** the page shows that the link is no longer valid

#### Scenario: Buyer edits an email
- **WHEN** a verified buyer changes a ticket's email address
- **THEN** the QR code and link are replaced, the ticket is emailed to the new address and the old address is notified

#### Scenario: Organizer edits an email
- **WHEN** an organizer changes an attendee's email address
- **THEN** the QR code and link are replaced, the ticket is emailed to the new address, the old address is notified and the change is recorded in the ticket's history

### Requirement: Buyer loses access to a transferred ticket
The system SHALL remove a ticket transferred to a different email address from the original buyer's order page and printable tickets, and SHALL show it there only as transferred, with the recipient's email masked and no QR code or link.

#### Scenario: Order page after a transfer
- **WHEN** the buyer opens their order page after one of its tickets was transferred
- **THEN** that ticket is listed as transferred with a masked recipient email and no QR code, link or actions

#### Scenario: Transferred back to the buyer
- **WHEN** a ticket is transferred to the order's own email address
- **THEN** it is shown on the order page as a normal ticket

#### Scenario: Print all tickets
- **WHEN** the buyer prints all tickets for the order
- **THEN** the transferred ticket is not printed

### Requirement: Transfer notifications are safe to send
The system SHALL send the transfer invitation without any text typed by the sender and without greeting the recipient by name.

#### Scenario: Invitation content
- **WHEN** the recipient receives the invitation
- **THEN** it names the event and ticket type and contains the claim link
- **AND** it contains no free-text message from the sender

#### Scenario: Buyer is told when a guest transfers
- **WHEN** a named guest transfers their ticket and the transfer is accepted
- **THEN** the order's buyer is emailed that the ticket was transferred

### Requirement: Organizer transfer controls
The system SHALL let organizers turn transfers on or off per event, set a transfer cutoff before the start time, set a maximum number of transfers per ticket, and mark individual ticket types as not transferable. Organizers SHALL be able to set the default on/off value for new events.

#### Scenario: Non-transferable ticket type
- **WHEN** a ticket type is marked not transferable
- **THEN** tickets of that type offer no transfer action while other types in the same event do

#### Scenario: Default for new events
- **WHEN** an organizer turns the default off and creates a new event
- **THEN** transfers are off for that event

#### Scenario: Existing event with self-service off
- **WHEN** an event had attendee self-service turned off before this feature was released
- **THEN** transfers are off for that event

### Requirement: Ticket history
The system SHALL record every transfer step and email change with its time, and SHALL show that history to organizers on the attendee and its order without revealing ticket links, QR code values or IP addresses.

#### Scenario: Organizer views history
- **WHEN** an organizer opens an attendee that was transferred
- **THEN** they see who it was transferred from and to, and when

#### Scenario: History hides credentials
- **WHEN** an organizer views the history of a ticket whose email was changed
- **THEN** the entry says the QR code and link were replaced but shows neither the old nor the new values

#### Scenario: Member without access
- **WHEN** a team member who cannot view the event requests a ticket's history
- **THEN** the request is refused

### Requirement: Transfer events for integrations
The system SHALL emit `attendee.transferred` when a transfer is accepted, and SHALL emit `attendee.updated` for every change of a ticket's name or email, including self-service changes.

#### Scenario: Webhook on transfer
- **WHEN** a transfer is accepted and a webhook subscribes to `attendee.transferred`
- **THEN** the webhook receives the attendee with its new holder details

#### Scenario: Self-service edit reaches integrations
- **WHEN** a buyer changes a ticket's name from the order page
- **THEN** `attendee.updated` is emitted
