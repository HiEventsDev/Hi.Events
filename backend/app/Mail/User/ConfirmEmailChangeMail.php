<?php

namespace HiEvents\Mail\User;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/user/confirm-email-change.blade.php
 */
class ConfirmEmailChangeMail extends BaseMail
{
    public function __construct(
        private readonly string $newEmail,
        private readonly string $token,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Confirm email change'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user.confirm-email-change',
            with: [
                'newEmail' => $this->newEmail,
                'link' => sprintf(Url::getFrontEndUrlFromConfig(Url::CONFIRM_EMAIL_CHANGE), $this->token),
            ]
        );
    }
}
