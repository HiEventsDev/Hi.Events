<?php

namespace HiEvents\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Values\MoneyValue;

/**
 * @uses /backend/resources/views/emails/orders/order-refunded.blade.php
 */
class OrderRefunded extends BaseMail
{
    private OrderDomainObject $orderDomainObject;

    private EventDomainObject $eventDomainObject;

    private MoneyValue $refundAmount;

    public function __construct(
        OrderDomainObject $order,
        EventDomainObject $event,
        MoneyValue        $refundAmount,
    )
    {
        parent::__construct();

        $this->orderDomainObject = $order;
        $this->eventDomainObject = $event;
        $this->refundAmount = $refundAmount;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve received a refund',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.order-refunded',
            with: [
                'event' => $this->eventDomainObject,
                'order' => $this->orderDomainObject,
                'refundAmount' => $this->refundAmount,
            ]
        );
    }
}
