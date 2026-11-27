<?php

namespace HiEvents\Mail\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Helper\StringHelper;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\Concerns\ProvidesEventContent;
use HiEvents\Services\Application\Handlers\Message\DTO\SendMessageDTO;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * @uses /backend/resources/views/emails/event/message.blade.php
 */
class EventMessage extends BaseMail implements ContainsEventContent
{
    use ProvidesEventContent;

    public function __construct(
        private readonly EventDomainObject $event,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly SendMessageDTO $messageData
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: ($this->messageData->is_test ? __('[Test]').' ' : '').$this->messageData->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.event.message',
            with: [
                'messageBody' => StringHelper::onOneLine($this->messageData->message),
                'event' => $this->event,
                'eventSettings' => $this->eventSettings,
            ]
        );
    }
}
