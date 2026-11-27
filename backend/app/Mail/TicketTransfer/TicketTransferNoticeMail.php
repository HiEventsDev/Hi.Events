<?php

namespace HiEvents\Mail\TicketTransfer;

use HiEvents\DomainObjects\Enums\TicketTransferNotice;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\Concerns\ProvidesEventContent;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/ticket-transfer/notice.blade.php
 */
class TicketTransferNoticeMail extends BaseMail implements ContainsEventContent
{
    use ProvidesEventContent;

    public function __construct(
        private readonly TicketTransferNotice $notice,
        private readonly EventDomainObject $event,
        private readonly OrganizerDomainObject $organizer,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly string $ticketTitle,
        private readonly string $maskedEmail,
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
            markdown: 'emails.ticket-transfer.notice',
            with: [
                'heading' => $this->notice->subject(),
                'summary' => $this->notice->summary($this->event->getTitle(), $this->ticketTitle, $this->maskedEmail),
                'asksToReportIfUnexpected' => $this->notice->asksToReportIfUnexpected(),
                'organizer' => $this->organizer,
                'eventSettings' => $this->eventSettings,
            ]
        );
    }
}
