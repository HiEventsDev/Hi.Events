<?php

namespace HiEvents\Enterprise\Sso\Mail;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/account/sso-break-glass-sign-in.blade.php
 */
class SsoBreakGlassSignInMail extends BaseMail
{
    public function __construct(
        private readonly string $ownerEmail,
        private readonly string $accountName,
        private readonly string $signedInAt,
        private readonly bool $recipientIsOwner,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->recipientIsOwner
                ? __('You signed in without single sign-on')
                : __('The account owner signed in without single sign-on'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account.sso-break-glass-sign-in',
            with: [
                'ownerEmail' => $this->ownerEmail,
                'accountName' => $this->accountName,
                'signedInAt' => $this->signedInAt,
                'recipientIsOwner' => $this->recipientIsOwner,
                'settingsUrl' => Url::getFrontEndUrlFromConfig(Url::ACCOUNT_SETTINGS),
            ]
        );
    }
}
