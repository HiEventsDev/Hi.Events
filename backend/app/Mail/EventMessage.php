<?php

namespace HiEvents\Mail;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Services\Handlers\Message\DTO\SendMessageDTO;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/event/message.blade.php
 */
class EventMessage extends BaseMail
{
    private SendMessageDTO $messageData;

    private EventDomainObject $event;

    public function __construct(EventDomainObject $event, SendMessageDTO $messageData)
    {
        parent::__construct();

        $this->messageData = $messageData;
        $this->event = $event;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->messageData->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.event.message',
            with: [
                'messageData' => $this->messageData,
                'event' => $this->event,
            ]
        );
    }
}
