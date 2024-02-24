<?php

namespace HiEvents\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Helper\Url;

/**
 * @uses /backend/resources/views/emails/auth/forgot-password.blade.php
 */
class ForgotPassword extends BaseMail
{
    private UserDomainObject $userDomainObject;

    private string $token;

    public function __construct(UserDomainObject $user, string $token)
    {
        parent::__construct();

        $this->userDomainObject = $user;
        $this->token = $token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password reset',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.forgot-password',
            with: [
                'user' => $this->userDomainObject,
                'link' => sprintf(Url::getFrontEndUrlFromConfig(Url::RESET_PASSWORD), $this->token),
            ]
        );
    }
}
