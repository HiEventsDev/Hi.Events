<?php

namespace HiEvents\Mail\Account;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/user/email-confirmation-code.blade.php
 */
class EmailConfirmationCodeEmail extends BaseMail
{
    private UserDomainObject $userDomainObject;

    private string $code;

    public function __construct(UserDomainObject $user, string $token)
    {
        parent::__construct();

        $this->userDomainObject = $user;
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
                'user' => $this->userDomainObject,
                'code' => $this->code,
            ]
        );
    }
}
