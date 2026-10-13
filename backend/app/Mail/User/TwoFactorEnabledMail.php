<?php

namespace HiEvents\Mail\User;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/user/two-factor-enabled.blade.php
 */
class TwoFactorEnabledMail extends BaseMail
{
    public function __construct()
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Two-factor authentication is now on'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user.two-factor-enabled',
            with: [
                'securityUrl' => Url::getFrontEndUrlFromConfig(Url::PROFILE_SECURITY),
            ]
        );
    }
}
