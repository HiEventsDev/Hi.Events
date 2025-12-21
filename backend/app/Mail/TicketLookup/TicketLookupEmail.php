<?php

namespace HiEvents\Mail\TicketLookup;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/ticket-lookup/ticket-lookup.blade.php
 */
class TicketLookupEmail extends BaseMail
{
    public function __construct(
        private readonly string $email,
        private readonly string $token,
        private readonly int $orderCount,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your Tickets'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ticket-lookup.ticket-lookup',
            with: [
                'email' => $this->email,
                'orderCount' => $this->orderCount,
                'ticketLookupUrl' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::TICKET_LOOKUP),
                    $this->token,
                ),
            ]
        );
    }
}
