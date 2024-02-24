<?php

namespace HiEvents\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;

/**
 * @uses /backend/resources/views/emails/orders/order-failed.blade.php
 */
class OrderFailed extends BaseMail
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
            subject: 'Your order wasn\'t successful',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.order-failed',
            with: [
                'event' => $this->eventDomainObject,
                'order' => $this->orderDomainObject,
            ]
        );
    }
}
