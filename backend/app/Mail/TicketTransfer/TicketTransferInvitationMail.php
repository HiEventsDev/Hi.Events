<?php

namespace HiEvents\Mail\TicketTransfer;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\Concerns\ProvidesEventContent;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/ticket-transfer/invitation.blade.php
 */
class TicketTransferInvitationMail extends BaseMail implements ContainsEventContent
{
    use ProvidesEventContent;

    public function __construct(
        private readonly EventDomainObject $event,
        private readonly OrganizerDomainObject $organizer,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly string $ticketTitle,
        private readonly string $maskedSenderEmail,
        private readonly string $claimUrl,
        private readonly string $expiresAtFormatted,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: __('You have been sent a ticket for :event', ['event' => $this->event->getTitle()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ticket-transfer.invitation',
            with: [
                'event' => $this->event,
                'organizer' => $this->organizer,
                'eventSettings' => $this->eventSettings,
                'ticketTitle' => $this->ticketTitle,
                'maskedSenderEmail' => $this->maskedSenderEmail,
                'claimUrl' => $this->claimUrl,
                'expiresAtFormatted' => $this->expiresAtFormatted,
            ]
        );
    }
}
