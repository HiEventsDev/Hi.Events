<?php

namespace HiEvents\Mail\Account;

use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/account/deletion-reminder.blade.php
 */
class AccountDeletionReminderEmail extends BaseMail
{
    public function __construct(
        private readonly string $accountName,
        private readonly string $scheduledDeletionDate,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Reminder: your :app_name account will be deleted on :date', [
                'app_name' => config('app.name'),
                'date' => $this->scheduledDeletionDate,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account.deletion-reminder',
            with: [
                'accountName' => $this->accountName,
                'scheduledDeletionDate' => $this->scheduledDeletionDate,
                'cancelLink' => Url::getFrontEndUrlFromConfig(Url::ACCOUNT_DANGER_ZONE),
            ],
        );
    }
}
