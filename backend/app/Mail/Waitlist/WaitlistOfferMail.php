<?php

namespace HiEvents\Mail\Waitlist;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WaitlistOfferMail extends BaseMail
{
    public function __construct(
        private readonly WaitlistEntryDomainObject  $entry,
        private readonly EventDomainObject          $event,
        private readonly ?ProductDomainObject        $product,
        private readonly ?ProductPriceDomainObject   $productPrice,
        private readonly OrganizerDomainObject      $organizer,
        private readonly EventSettingDomainObject   $eventSettings,
        private readonly string                     $orderShortId,
        private readonly string                     $sessionIdentifier,
    )
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: __('A spot has opened up for :event!', ['event' => $this->event->getTitle()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.waitlist.offer',
            with: [
                'entry' => $this->entry,
                'event' => $this->event,
                'productName' => $this->buildProductName(),
                'organizer' => $this->organizer,
                'eventSettings' => $this->eventSettings,
                'offerExpiresAtFormatted' => $this->formatOfferExpiry(),
                'checkoutUrl' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::ORDER_DETAILS, [
                        'session_identifier' => $this->sessionIdentifier,
                        'waitlist' => 'true',
                    ]),
                    $this->event->getId(),
                    $this->orderShortId,
                ),
            ]
        );
    }

    private function formatOfferExpiry(): ?string
    {
        $expiresAt = $this->entry->getOfferExpiresAt();

        if ($expiresAt === null) {
            return null;
        }

        return Carbon::parse($expiresAt)->isoFormat('MMMM D, YYYY [at] h:mm A (z)');
    }

    private function buildProductName(): ?string
    {
        if (!$this->product) {
            return null;
        }

        $name = $this->product->getTitle();

        if ($this->productPrice?->getLabel()) {
            $name .= ' - ' . $this->productPrice->getLabel();
        }

        return $name;
    }
}
