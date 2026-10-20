<?php

namespace HiEvents\Mail\Account;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/account/admin-access-granted.blade.php
 */
class AdminAccessGrantedMail extends BaseMail
{
    public function __construct(
        private readonly string $newAdminEmail,
        private readonly ?string $grantedByEmail = null,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('A new admin was added to your account'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account.admin-access-granted',
            with: [
                'newAdminEmail' => $this->newAdminEmail,
                'grantedByEmail' => $this->grantedByEmail,
                'teamUrl' => Url::getFrontEndUrlFromConfig(Url::ACCOUNT_USERS),
            ]
        );
    }
}
