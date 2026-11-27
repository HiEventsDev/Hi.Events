<?php

namespace HiEvents\Mail\TicketResale;

use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\Concerns\ProvidesEventContent;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/ticket-resale/notice.blade.php
 */
class TicketResaleNoticeMail extends BaseMail implements ContainsEventContent
{
    use ProvidesEventContent;

    public function __construct(
        private readonly TicketResaleNotice $notice,
        private readonly EventDomainObject $event,
        private readonly OrganizerDomainObject $organizer,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly string $ticketTitle,
        private readonly ?string $formattedAmount = null,
        private readonly ?string $actionUrl = null,
        private readonly ?string $actionLabel = null,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: $this->notice->subject(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ticket-resale.notice',
            with: [
                'heading' => $this->notice->subject(),
                'summary' => $this->notice->summary($this->event->getTitle(), $this->ticketTitle, $this->formattedAmount),
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
                'isForOrganizer' => $this->notice->isForOrganizer(),
                'organizer' => $this->organizer,
                'eventSettings' => $this->eventSettings,
            ]
        );
    }

    public function isSentToBuyers(): bool
    {
        return ! $this->notice->isForOrganizer();
    }
}
