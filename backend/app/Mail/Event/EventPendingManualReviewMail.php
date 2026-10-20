<?php

namespace HiEvents\Mail\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/event/event-pending-manual-review.blade.php
 */
class EventPendingManualReviewMail extends BaseMail
{
    public function __construct(
        private readonly EventDomainObject $event,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your event ":title" is pending review', [
                'title' => $this->event->getTitle(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.event.event-pending-manual-review',
            with: [
                'event' => $this->event,
            ]
        );
    }
}
