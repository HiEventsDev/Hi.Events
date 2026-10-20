<?php

namespace HiEvents\Mail\Account;

use Carbon\Carbon;
use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\AccountDeletionOutcome;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/account/deletion-requested.blade.php
 */
class AccountDeletionRequestedEmail extends BaseMail
{
    public function __construct(
        private readonly AccountDomainObject $account,
        private readonly AccountDeletionRequestDomainObject $deletionRequest,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your :app_name account is scheduled for deletion', [
                'app_name' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account.deletion-requested',
            with: [
                'account' => $this->account,
                'scheduledDeletionDate' => Carbon::parse($this->deletionRequest->getScheduledDeletionAt())
                    ->setTimezone($this->account->getTimezone() ?? 'UTC')
                    ->toFormattedDateString(),
                'willBeAnonymized' => $this->deletionRequest->getExpectedOutcome() === AccountDeletionOutcome::ANONYMIZE->name,
                'cancelLink' => Url::getFrontEndUrlFromConfig(Url::ACCOUNT_DANGER_ZONE),
            ],
        );
    }
}
