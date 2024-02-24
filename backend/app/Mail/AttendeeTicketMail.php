<?php

namespace HiEvents\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;

/**
 * @uses /backend/resources/views/emails/orders/attendee-ticket.blade.php
 */
class AttendeeTicketMail extends BaseMail
{
    private EventDomainObject $event;
    private AttendeeDomainObject $attendee;

    public function __construct(AttendeeDomainObject $attendee, EventDomainObject $event)
    {
        parent::__construct();

        $this->event = $event;
        $this->attendee = $attendee;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('🎟️ Your Ticket for :event', [
                'event' => Str::limit($this->event->getTitle(), 50)
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.attendee-ticket',
            with: [
                'event' => $this->event,
                'attendee' => $this->attendee,
            ]
        );
    }
}
