<?php

namespace HiEvents\Mail\User;

use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/auth/reset-password-success.blade.php
 */
class ResetPasswordSuccess extends BaseMail
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your password has been reset'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.reset-password-success',
        );
    }
}
