<?php

namespace HiEvents\Mail\SelfService;

use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/self-service/verification-code.blade.php
 */
class SelfServiceVerificationCodeMail extends BaseMail
{
    public function __construct(
        private readonly string $code,
        private readonly int $validForMinutes,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your verification code'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.self-service.verification-code',
            with: [
                'code' => $this->code,
                'validForMinutes' => $this->validForMinutes,
            ]
        );
    }
}
