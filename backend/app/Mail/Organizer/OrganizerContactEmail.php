<?php

namespace HiEvents\Mail\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrganizerContactEmail extends BaseMail
{
    public function __construct(
        private readonly OrganizerDomainObject $organizer,
        private readonly string                $senderName,
        private readonly string                $senderEmail,
        private readonly string                $messageContent,
    )
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: __('New message from :name', ['name' => $this->senderName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organizer.contact-message',
            with: [
                'organizerName' => $this->organizer->getName(),
                'senderName' => $this->senderName,
                'senderEmail' => $this->senderEmail,
                'replySubject' => urlencode(__('Response from :organizerName', [
                    'organizerName' => $this->organizer->getName(),
                ])),
                'messageContent' => $this->messageContent,
            ],
        );
    }
}
