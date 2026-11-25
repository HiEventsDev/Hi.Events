# Spec Delta

## Purpose

Lets a ticket buyer or holder prove they control the email address on file before a sensitive self-service action, and lets anyone find their tickets by email without an account.

## ADDED Requirements

### Requirement: Email code verification
The system SHALL send a six-digit code to the email address on file for an order or ticket when a person holding that order's or ticket's link asks to verify. The person SHALL NOT supply the address. The response SHALL show the address only in masked form.

#### Scenario: Code requested from an order link
- **WHEN** a person on an order page requests verification
- **THEN** a six-digit code is emailed to the order's email address
- **AND** the response contains the address masked, such as `d•••@example.com`

#### Scenario: Code requested from a ticket link
- **WHEN** a person on a ticket page requests verification
- **THEN** a six-digit code is emailed to that ticket's attendee email address

#### Scenario: Ticket has no email on file
- **WHEN** verification is requested for a ticket whose attendee has no email address
- **THEN** the request is refused with a message that this ticket cannot be managed online

### Requirement: Verified session
The system SHALL exchange a correct, unexpired code for a session that is bound to the verified email address and lasts 30 minutes. A code SHALL expire after 10 minutes and after 5 wrong attempts.

#### Scenario: Correct code
- **WHEN** the person enters the code they were sent within 10 minutes
- **THEN** they receive a verified session for that email address

#### Scenario: Wrong code
- **WHEN** the person enters an incorrect code
- **THEN** no session is issued and they are told the code is incorrect

#### Scenario: Too many attempts
- **WHEN** an incorrect code is entered a fifth time
- **THEN** the code stops working and a new one must be requested

#### Scenario: Session expires
- **WHEN** a sensitive action is attempted more than 30 minutes after verification
- **THEN** the action is refused and the person is asked to verify again

### Requirement: Lookup link counts as verified
The system SHALL treat a person who arrives through a valid "look up my tickets" link as verified for the email address that link was sent to.

#### Scenario: Acting from the my-tickets page
- **WHEN** a person opens their my-tickets link and starts a transfer
- **THEN** they are not asked for a code

### Requirement: Sensitive actions require a verified session
The system SHALL refuse to start or cancel a transfer, list or withdraw a resale, or change a ticket's or an order's email address unless the request carries a verified session whose email controls that ticket.

#### Scenario: No session
- **WHEN** a transfer is requested with only an order link and no verified session
- **THEN** the request is refused and the person is prompted to verify

#### Scenario: Session for a different email
- **WHEN** a verified session for one email is used on a ticket controlled by another email
- **THEN** the request is refused

#### Scenario: Name-only edit
- **WHEN** a buyer changes only the first or last name on a ticket from the order page
- **THEN** the change is accepted without a verified session, as before

#### Scenario: Email edit without a session
- **WHEN** a buyer changes a ticket's email address from the order page without a verified session
- **THEN** the change is refused with a validation error on the email field

#### Scenario: Order email edit without a session
- **WHEN** a person holding only the order link changes the order's email address
- **THEN** the change is refused with a validation error on the email field
- **AND** a later verification code still goes to the original address

### Requirement: Ticket control
The system SHALL treat a verified email as controlling a ticket when it is the ticket's attendee email, or when it is the order's email and the ticket has never been transferred.

#### Scenario: Buyer controls an untransferred ticket
- **WHEN** the order's email is verified and the ticket has never been transferred
- **THEN** the buyer can act on that ticket even if the attendee email is different

#### Scenario: Buyer loses control after a transfer
- **WHEN** the order's email is verified and the ticket has been transferred to another person
- **THEN** the buyer cannot act on that ticket

### Requirement: Verification abuse limits
The system SHALL rate-limit code requests and code attempts per order or ticket link and per IP address.

#### Scenario: Repeated code requests
- **WHEN** codes are requested for the same order more often than the limit allows
- **THEN** further requests are refused until the limit window passes

### Requirement: Ticket lookup by attendee email
The system SHALL include, in the "look up my tickets" result for an email address, tickets whose attendee email is that address, in addition to orders placed with that address.

#### Scenario: Transfer recipient looks up tickets
- **WHEN** a person who received a ticket by transfer requests a lookup link for their email
- **THEN** the my-tickets page lists that ticket with a link to it

#### Scenario: Named guest looks up tickets
- **WHEN** a person named on a ticket in someone else's order requests a lookup link for their email
- **THEN** the my-tickets page lists only their own ticket, not the rest of the order
