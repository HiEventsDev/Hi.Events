<?php

namespace HiEvents\Mail\Admin;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/admin/event-flagged-as-spam.blade.php
 */
class EventFlaggedAsSpamMail extends BaseMail
{
    public function __construct(
        private readonly EventDomainObject $event,
        private readonly AccountDomainObject $account,
        private readonly array $verdict,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('[Action Required] Event Flagged as Spam - :title', [
                'title' => $this->event->getTitle(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin.event-flagged-as-spam',
            with: [
                'event' => $this->event,
                'account' => $this->account,
                'verdict' => $this->verdict,
                'reviewUrl' => config('app.frontend_url').'/admin/spam-events',
            ]
        );
    }
}
