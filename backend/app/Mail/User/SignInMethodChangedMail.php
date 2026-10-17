<?php

namespace HiEvents\Mail\User;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/user/sign-in-method-changed.blade.php
 */
class SignInMethodChangedMail extends BaseMail
{
    public function __construct(
        private readonly string $providerName,
        private readonly bool $connected,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->connected
                ? __(':provider sign-in was connected to your account', ['provider' => $this->providerName])
                : __(':provider sign-in was removed from your account', ['provider' => $this->providerName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user.sign-in-method-changed',
            with: [
                'providerName' => $this->providerName,
                'connected' => $this->connected,
                'securityUrl' => Url::getFrontEndUrlFromConfig(Url::PROFILE_SECURITY),
            ]
        );
    }
}
