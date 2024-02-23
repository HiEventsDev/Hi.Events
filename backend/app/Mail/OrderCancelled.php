<?php

namespace TicketKitten\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\OrderDomainObject;

/**
 * @uses /backend/resources/views/emails/orders/order-cancelled.blade.php
 */
class OrderCancelled extends BaseMail
{
    public function __construct(
        private readonly OrderDomainObject $order,
        private readonly EventDomainObject $event,
    )
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your order has been cancelled',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.order-cancelled',
            with: [
                'event' => $this->event,
                'order' => $this->order,
            ]
        );
    }
}
