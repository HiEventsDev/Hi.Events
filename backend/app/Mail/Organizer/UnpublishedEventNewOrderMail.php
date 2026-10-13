<?php

namespace HiEvents\Mail\Organizer;

use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/orders/organizer/unpublished-event-new-order.blade.php
 */
class UnpublishedEventNewOrderMail extends BaseMail
{
    public function __construct(
        private readonly string $orderUrl,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New order on an unpublished event'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.organizer.unpublished-event-new-order',
            with: [
                'orderUrl' => $this->orderUrl,
                'appName' => config('app.name'),
            ],
        );
    }
}
