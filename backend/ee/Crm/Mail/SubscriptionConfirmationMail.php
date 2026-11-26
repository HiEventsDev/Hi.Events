<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Mail;

use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/crm/subscription-confirmation.blade.php
 */
class SubscriptionConfirmationMail extends BaseMail
{
    use FlattensLines;

    private readonly ?string $organizerName;

    public function __construct(
        ?string $organizerName,
        private readonly string $confirmUrl,
        private readonly int $expiresInDays,
    ) {
        parent::__construct();

        $this->organizerName = $organizerName === null ? null : $this->onOneLine($organizerName);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->organizerName === null
                ? __('Confirm your subscription')
                : __('Confirm your subscription to :organizer', ['organizer' => $this->organizerName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.crm.subscription-confirmation',
            with: [
                'organizerName' => $this->organizerName,
                'confirmUrl' => $this->confirmUrl,
                'expiresInDays' => $this->expiresInDays,
            ],
        );
    }
}
