<?php

namespace HiEvents\Mail\Organizer;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/**
 * @uses /backend/resources/views/emails/orders/organizer/summary-for-organizer.blade.php
 */
class OrderSummaryForOrganizer extends BaseMail
{
    private OrderDomainObject $order;

    private EventDomainObject $event;

    public function __construct(OrderDomainObject $order, EventDomainObject $event)
    {
        parent::__construct();

        $this->order = $order;
        $this->event = $event;
    }

    public function envelope(): Envelope
    {
        $subject = $this->order->getTotalGross() > 0
            ? __('New order for :amount for :event 🎉', [
                    'amount' => Currency::format($this->order->getTotalGross(), $this->event->getCurrency()),
                    'event' => Str::limit($this->event->getTitle(), 75)]
            )
            : __('New order for :event 🎉', ['event' => Str::limit($this->event->getTitle(), 75)]);

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.organizer.summary-for-organizer',
            with: [
                'event' => $this->event,
                'order' => $this->order,
                'orderUrl' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::ORGANIZER_ORDER_SUMMARY),
                    $this->event->getId(),
                    $this->order->getId(),
                )
            ]
        );
    }
}
