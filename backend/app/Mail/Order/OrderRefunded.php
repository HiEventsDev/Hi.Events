<?php

namespace HiEvents\Mail\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Mail\BaseMail;
use HiEvents\Values\MoneyValue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/orders/order-refunded.blade.php
 */
class OrderRefunded extends BaseMail
{
    public function __construct(
        private readonly OrderDomainObject $order,
        private readonly EventDomainObject $event,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly MoneyValue        $refundAmount,
    )
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: __('You\'ve received a refund'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.order-refunded',
            with: [
                'event' => $this->event,
                'order' => $this->order,
                'eventSettings' => $this->eventSettings,
                'refundAmount' => $this->refundAmount,
            ]
        );
    }
}
