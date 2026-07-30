<?php

namespace HiEvents\Mail\Account;

use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/account/deletion-completed.blade.php
 */
class AccountDeletionCompletedEmail extends BaseMail
{
    public function __construct(
        private readonly string $accountName,
        private readonly bool $wasAnonymized,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your :app_name account has been deleted', [
                'app_name' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account.deletion-completed',
            with: [
                'accountName' => $this->accountName,
                'wasAnonymized' => $this->wasAnonymized,
            ],
        );
    }
}
