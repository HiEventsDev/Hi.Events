<?php

namespace HiEvents\Enterprise\Sso\Mail;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/account/sso-settings-changed.blade.php
 */
class SsoSettingsChangedMail extends BaseMail
{
    public function __construct(
        private readonly string $actorEmail,
        private readonly string $change,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your single sign-on settings were changed'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account.sso-settings-changed',
            with: [
                'actorEmail' => $this->actorEmail,
                'change' => $this->change,
                'settingsUrl' => Url::getFrontEndUrlFromConfig(Url::ACCOUNT_SETTINGS),
            ]
        );
    }
}
