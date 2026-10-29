<?php

namespace HiEvents\Mail\Organizer;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\Concerns\ProvidesEventContent;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/**
 * @uses /backend/resources/views/emails/orders/organizer/offline-payment-reported.blade.php
 */
class OfflinePaymentReportedMail extends BaseMail implements ContainsEventContent
{
    use ProvidesEventContent;

    public function __construct(
        private readonly OrderDomainObject $order,
        private readonly EventDomainObject $event,
    ) {
        parent::__construct();
    }

    public function isSentToBuyers(): bool
    {
        return false;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Payment reported for order :order (:event)', [
                'order' => $this->order->getPublicId(),
                'event' => Str::limit($this->event->getTitle(), 75),
            ]),
        );
    }

    public function withheldEventContentReplacement(): BaseMail
    {
        return new UnpublishedEventNewOrderMail($this->orderUrl());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.organizer.offline-payment-reported',
            with: [
                'event' => $this->event,
                'order' => $this->order,
                'amount' => Currency::format($this->order->getTotalGross(), $this->order->getCurrency()),
                'orderUrl' => $this->orderUrl(),
            ]
        );
    }

    private function orderUrl(): string
    {
        return sprintf(
            Url::getFrontEndUrlFromConfig(Url::ORGANIZER_ORDER_SUMMARY),
            $this->event->getId(),
            $this->order->getId(),
        );
    }
}
