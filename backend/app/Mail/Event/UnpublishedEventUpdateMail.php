<?php

namespace HiEvents\Mail\Event;

use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/event/unpublished-event-update.blade.php
 */
class UnpublishedEventUpdateMail extends BaseMail
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('An update about your booking on :appName', ['appName' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.event.unpublished-event-update',
            with: [
                'appName' => config('app.name'),
            ],
        );
    }
}
