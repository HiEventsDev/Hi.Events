<?php

namespace HiEvents\Mail\User;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/user/email-change-requested.blade.php
 */
class EmailChangeRequestedMail extends BaseMail
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('A change to your email address was requested'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user.email-change-requested',
            with: [
                'securityUrl' => Url::getFrontEndUrlFromConfig(Url::PROFILE_SECURITY),
            ]
        );
    }
}
