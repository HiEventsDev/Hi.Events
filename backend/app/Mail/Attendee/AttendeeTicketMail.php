<?php

namespace HiEvents\Mail\Attendee;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\StringHelper;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

/**
 * @uses /backend/resources/views/emails/orders/attendee-ticket.blade.php
 */
class AttendeeTicketMail extends BaseMail
{
    public function __construct(
        private readonly AttendeeDomainObject     $attendee,
        private readonly EventDomainObject        $event,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly OrganizerDomainObject    $organizer,
    )
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: __('🎟️ Your Ticket for :event', [
                'event' => Str::limit($this->event->getTitle(), 50)
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.attendee-ticket',
            with: [
                'event' => $this->event,
                'attendee' => $this->attendee,
                'eventSettings' => $this->eventSettings,
                'organizer' => $this->organizer,
                'ticketUrl' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::ATTENDEE_TICKET),
                    $this->event->getId(),
                    $this->attendee->getShortId(),
                )
            ]
        );
    }

    public function attachments(): array
    {
        $startDateTime = Carbon::parse($this->event->getStartDate(), $this->event->getTimezone());
        $endDateTime = $this->event->getEndDate() ? Carbon::parse($this->event->getEndDate(), $this->event->getTimezone()) : null;

        $event = Event::create()
            ->name($this->event->getTitle())
            ->uniqueIdentifier('event-' . $this->attendee->getId())
            ->startsAt($startDateTime)
            ->url($this->event->getEventUrl())
            ->organizer($this->organizer->getEmail(), $this->organizer->getName());

        if ($this->event->getDescription()) {
            $event->description(StringHelper::previewFromHtml($this->event->getDescription()));
        }

        if ($this->eventSettings->getLocationDetails()) {
            $event->address($this->eventSettings->getAddressString());
        }

        if ($endDateTime) {
            $event->endsAt($endDateTime);
        }

        $calendar = Calendar::create()
            ->event($event)
            ->get();

        return [
            Attachment::fromData(static fn() => $calendar, 'event.ics')
                ->withMime('text/calendar')
        ];
    }
}
