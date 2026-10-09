<?php

namespace HiEvents\Mail\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/user/two-factor-disabled.blade.php
 */
class TwoFactorDisabledMail extends BaseMail
{
    public function __construct(
        private readonly UserDomainObject $user,
        private readonly bool $resetByAdministrator = false,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Two-factor authentication has been turned off'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user.two-factor-disabled',
            with: [
                'user' => $this->user,
                'resetByAdministrator' => $this->resetByAdministrator,
                'securityUrl' => Url::getFrontEndUrlFromConfig(Url::PROFILE_SECURITY),
            ]
        );
    }
}
