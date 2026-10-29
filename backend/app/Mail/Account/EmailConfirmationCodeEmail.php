<?php

namespace HiEvents\Mail\Account;

use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/user/email-confirmation-code.blade.php
 */
class EmailConfirmationCodeEmail extends BaseMail
{
    private string $code;

    public function __construct(string $token)
    {
        parent::__construct();

        $this->code = $token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your confirmation code for :app_name is :code', [
                'app_name' => config('app.name'),
                'code' => $this->code,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user.email-confirmation-code',
            with: [
                'code' => $this->code,
            ]
        );
    }
}
