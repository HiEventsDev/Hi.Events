<?php

namespace TicketKitten\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\OrderDomainObject;

/**
 * @uses /backend/resources/views/emails/orders/summary.blade.php
 */
class OrderSummary extends BaseMail
{
    private OrderDomainObject $orderDomainObject;

    private EventDomainObject $eventDomainObject;

    public function __construct(OrderDomainObject $order, EventDomainObject $event)
    {
        parent::__construct();

        $this->orderDomainObject = $order;
        $this->eventDomainObject = $event;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Order is Confirmed! 🎉',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.summary',
            with: [
                'event' => $this->eventDomainObject,
                'order' => $this->orderDomainObject,
            ]
        );
    }
}
