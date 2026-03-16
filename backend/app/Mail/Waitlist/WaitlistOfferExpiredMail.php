<?php

namespace HiEvents\Mail\Waitlist;

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

class WaitlistOfferExpiredMail extends BaseMail
{
    public function __construct(
        private readonly WaitlistEntryDomainObject  $entry,
        private readonly EventDomainObject          $event,
        private readonly ?ProductDomainObject        $product,
        private readonly ?ProductPriceDomainObject   $productPrice,
        private readonly OrganizerDomainObject      $organizer,
        private readonly EventSettingDomainObject   $eventSettings,
    )
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: __('Your waitlist offer has expired'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.waitlist.offer-expired',
            with: [
                'entry' => $this->entry,
                'event' => $this->event,
                'productName' => $this->buildProductName(),
                'organizer' => $this->organizer,
                'eventSettings' => $this->eventSettings,
                'eventUrl' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::EVENT_HOMEPAGE),
                    $this->event->getId(),
                    $this->event->getSlug(),
                ) . '?clear_waitlist=true',
            ]
        );
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
