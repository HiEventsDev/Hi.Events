<?php

namespace HiEvents\Mail\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\Concerns\ProvidesEventContent;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
use HiEvents\Services\Domain\Lineup\DTO\EmailLineupHighlightsDTO;
use HiEvents\Services\Domain\Order\InvoicePdfRenderer;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/orders/summary.blade.php
 */
class OrderSummary extends BaseMail implements ContainsEventContent
{
    use ProvidesEventContent;

    private readonly ?RenderedEmailTemplateDTO $renderedTemplate;

    private ?EmailLineupHighlightsDTO $lineup = null;

    public function __construct(
        private readonly OrderDomainObject $order,
        private readonly EventDomainObject $event,
        private readonly OrganizerDomainObject $organizer,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly ?InvoiceDomainObject $invoice,
        ?RenderedEmailTemplateDTO $renderedTemplate = null,
        ?EmailLineupHighlightsDTO $lineup = null,
    ) {
        $this->renderedTemplate = $renderedTemplate;
        $this->lineup = $lineup;

        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $subject = $this->renderedTemplate?->subject ?? __('Your Order is Confirmed!').'  🎉';

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
            markdown: 'emails.orders.summary',
            with: [
                'eventSettings' => $this->eventSettings,
                'event' => $this->event,
                'order' => $this->order,
                'organizer' => $this->organizer,
                'occurrence' => $this->order->getSingleEventOccurrence(),
                'lineup' => $this->lineup,
                'orderUrl' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::ORDER_SUMMARY),
                    $this->event->getId(),
                    $this->order->getShortId(),
                ),
            ]
        );
    }

    public function attachments(): array
    {
        if ($this->invoice === null) {
            return [];
        }

        $invoice = app(InvoicePdfRenderer::class)->render(
            order: $this->order,
            event: $this->event,
            organizer: $this->organizer,
            eventSettings: $this->eventSettings,
            invoice: $this->invoice,
        );

        return [
            Attachment::fromData(
                static fn () => $invoice->output(),
                'invoice.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
