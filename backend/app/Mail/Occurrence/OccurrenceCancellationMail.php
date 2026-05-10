<?php

namespace HiEvents\Mail\Occurrence;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OccurrenceCancellationMail extends BaseMail
{
    private readonly ?RenderedEmailTemplateDTO $renderedTemplate;

    public function __construct(
        private readonly EventDomainObject           $event,
        private readonly EventOccurrenceDomainObject $occurrence,
        private readonly OrganizerDomainObject       $organizer,
        private readonly EventSettingDomainObject    $eventSettings,
        private readonly string                      $formattedDate,
        private readonly bool                        $refundOrders = false,
        ?RenderedEmailTemplateDTO                    $renderedTemplate = null,
    )
    {
        $this->renderedTemplate = $renderedTemplate;
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $subject = $this->renderedTemplate?->subject ?? __(':event on :date has been cancelled', [
            'event' => $this->event->getTitle(),
            'date' => $this->formattedDate,
        ]);

        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        if ($this->renderedTemplate) {
            return new Content(
                markdown: 'emails.custom-template',
                with: [
                    'renderedBody' => $this->renderedTemplate->body,
                    'renderedCta' => $this->renderedTemplate->cta,
                    'eventSettings' => $this->eventSettings,
                ]
            );
        }

        return new Content(
            markdown: 'emails.occurrence.cancellation',
            with: [
                'event' => $this->event,
                'occurrence' => $this->occurrence,
                'organizer' => $this->organizer,
                'eventSettings' => $this->eventSettings,
                'formattedDate' => $this->formattedDate,
                'refundOrders' => $this->refundOrders,
                'eventUrl' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::EVENT_HOMEPAGE),
                    $this->event->getId(),
                    $this->event->getSlug(),
                ),
            ]
        );
    }
}
