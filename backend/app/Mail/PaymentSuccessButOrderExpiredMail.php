<?php

namespace TicketKitten\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\OrderDomainObject;

/**
 * @uses /backend/resources/views/emails/orders/payment-success-but-order-expired.blade.php
 */
class PaymentSuccessButOrderExpiredMail extends BaseMail
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
            subject: __('We were unable to process your order'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.payment-success-but-order-expired',
            with: [
                'event' => $this->eventDomainObject,
                'order' => $this->orderDomainObject,
            ]
        );
    }
}
