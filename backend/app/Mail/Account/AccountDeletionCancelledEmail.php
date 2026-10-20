<?php

namespace HiEvents\Mail\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/account/deletion-cancelled.blade.php
 */
class AccountDeletionCancelledEmail extends BaseMail
{
    public function __construct(
        private readonly AccountDomainObject $account,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your :app_name account deletion has been cancelled', [
                'app_name' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account.deletion-cancelled',
            with: [
                'account' => $this->account,
            ],
        );
    }
}
