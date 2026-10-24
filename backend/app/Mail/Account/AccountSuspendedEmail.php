<?php

namespace HiEvents\Mail\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/account/suspended.blade.php
 */
class AccountSuspendedEmail extends BaseMail
{
    public function __construct(
        private readonly AccountDomainObject $account,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your :app_name account has been suspended', [
                'app_name' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account.suspended',
            with: [
                'reason' => $this->account->getSuspensionReason(),
                'supportEmail' => config('app.platform_support_email'),
            ],
        );
    }
}
