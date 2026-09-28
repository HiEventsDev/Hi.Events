<?php

namespace HiEvents\Mail\Cashless;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\BaseMail;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/**
 * @uses /backend/resources/views/emails/cashless/topup-confirmation.blade.php
 */
class CashlessTopupConfirmationMail extends BaseMail
{
    public function __construct(
        private readonly AttendeeDomainObject $attendee,
        private readonly EventDomainObject $event,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly OrganizerDomainObject $organizer,
        private readonly string $toppedUpAmount,
        private readonly string $newBalance,
        private readonly string $walletUrl,
        private readonly ?RenderedEmailTemplateDTO $renderedTemplate = null,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $subject = $this->renderedTemplate?->subject ?? __('💳 Your cashless balance for :event', [
            'event' => Str::limit($this->event->getTitle(), 50),
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
            markdown: 'emails.cashless.topup-confirmation',
            with: [
                'event' => $this->event,
                'eventSettings' => $this->eventSettings,
                'organizer' => $this->organizer,
                'attendee' => $this->attendee,
                'toppedUpAmount' => $this->toppedUpAmount,
                'newBalance' => $this->newBalance,
                'walletUrl' => $this->walletUrl,
            ]
        );
    }
}
