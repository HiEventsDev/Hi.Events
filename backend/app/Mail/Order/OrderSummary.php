<?php

namespace HiEvents\Mail\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/orders/summary.blade.php
 */
class OrderSummary extends BaseMail
{
    public function __construct(
        private readonly OrderDomainObject     $order,
        private readonly EventDomainObject     $event,
        private readonly OrganizerDomainObject $organizer,
    )
    {
        parent::__construct();
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
                'event' => $this->event,
                'order' => $this->order,
                'organizer' => $this->organizer,
                'orderUrl' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::ORDER_SUMMARY),
                    $this->event->getId(),
                    $this->order->getShortId(),
                )
            ]
        );
    }
}
