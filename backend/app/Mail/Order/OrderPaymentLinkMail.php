<?php

namespace HiEvents\Mail\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\Concerns\ProvidesEventContent;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/**
 * @uses /backend/resources/views/emails/orders/payment-link.blade.php
 */
class OrderPaymentLinkMail extends BaseMail implements ContainsEventContent
{
    use ProvidesEventContent;

    public function __construct(
        private readonly OrderDomainObject $order,
        private readonly EventDomainObject $event,
        private readonly OrganizerDomainObject $organizer,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly string $paymentLinkUrl,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: __('Payment link for your :event order', ['event' => Str::limit($this->event->getTitle(), 75)]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.payment-link',
            with: [
                'event' => $this->event,
                'order' => $this->order,
                'organizer' => $this->organizer,
                'eventSettings' => $this->eventSettings,
                'paymentLinkUrl' => $this->paymentLinkUrl,
                'amountDue' => Currency::format($this->order->getTotalGross(), $this->order->getCurrency()),
                'payByFormatted' => Carbon::parse($this->order->getReservedUntil(), 'UTC')
                    ->setTimezone($this->event->getTimezone())
                    ->isoFormat('dddd, MMMM D · h:mm A z'),
            ]
        );
    }
}
