<?php

namespace HiEvents\Mail\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountApprovalRequestEmail extends BaseMail
{
    public function __construct(
        private readonly UserDomainObject    $user,
        private readonly AccountDomainObject $account,
        private readonly string              $approveUrl,
    )
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('New account registration requires approval: :email', [
                'email' => $this->user->getEmail(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.account-approval-request',
            with: [
                'user' => $this->user,
                'account' => $this->account,
                'approveUrl' => $this->approveUrl,
            ]
        );
    }
}
