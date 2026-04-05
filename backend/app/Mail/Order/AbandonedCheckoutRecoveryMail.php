<?php

namespace HiEvents\Mail\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AbandonedCheckoutRecoveryMail extends BaseMail
{
    public function __construct(
        private readonly OrderDomainObject        $order,
        private readonly EventDomainObject        $event,
        private readonly OrganizerDomainObject    $organizer,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly string                   $recoveryToken,
        private readonly ?string                  $promoCode = null,
    )
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: __('You left something behind! Complete your order for :event', [
                'event' => $this->event->getTitle(),
            ]),
        );
    }

    public function content(): Content
    {
        $recoveryUrl = sprintf(
            '%s/event/%d/order/%s?recovery=%s',
            rtrim(Url::getFrontEndUrlFromConfig(Url::FRONTEND_URL), '/'),
            $this->event->getId(),
            $this->order->getShortId(),
            $this->recoveryToken,
        );

        return new Content(
            markdown: 'emails.orders.abandoned-checkout-recovery',
            with: [
                'event' => $this->event,
                'order' => $this->order,
                'organizer' => $this->organizer,
                'eventSettings' => $this->eventSettings,
                'recoveryUrl' => $recoveryUrl,
                'promoCode' => $this->promoCode,
            ]
        );
    }
}
