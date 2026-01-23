<?php

namespace HiEvents\Mail\Admin;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/admin/message-pending-review.blade.php
 */
class MessagePendingReviewMail extends BaseMail
{
    public function __construct(
        private readonly MessageDomainObject $message,
        private readonly EventDomainObject $event,
        private readonly AccountDomainObject $account,
        private readonly array $failures
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('[Action Required] Message Pending Review - :subject', [
                'subject' => $this->message->getSubject()
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.message-pending-review',
            with: [
                'message' => $this->message,
                'event' => $this->event,
                'account' => $this->account,
                'failures' => $this->failures,
                'reviewUrl' => config('app.frontend_url') . '/admin/messages?status=PENDING_REVIEW',
            ]
        );
    }
}
