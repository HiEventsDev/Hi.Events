<?php

namespace HiEvents\Mail\Attendee;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Helper\AddressHelper;
use HiEvents\Helper\StringHelper;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
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
    private readonly ?RenderedEmailTemplateDTO $renderedTemplate;

    public function __construct(
        private readonly OrderDomainObject $order,
        private readonly AttendeeDomainObject $attendee,
        private readonly EventDomainObject $event,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly OrganizerDomainObject $organizer,
        ?RenderedEmailTemplateDTO $renderedTemplate = null,
        private readonly ?EventOccurrenceDomainObject $occurrence = null,
    ) {
        parent::__construct();
        $this->renderedTemplate = $renderedTemplate;
    }

    public function envelope(): Envelope
    {
        $subject = $this->renderedTemplate?->subject ?? __('🎟️ Your Ticket for :event', [
            'event' => Str::limit($this->event->getTitle(), 50),
        ]);

        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        if ($this->renderedTemplate) {
            return new Content(
                markdown: 'emails.custom-template',
                with: [
                    'renderedBody' => $this->renderedTemplate->body,
                    'renderedCta' => $this->renderedTemplate->cta,
                    'eventSettings' => $this->eventSettings,
                ]
            );
        }

        $occurrence = $this->occurrence ?? $this->attendee->getEventOccurrence();
        $eventLocation = $occurrence?->getEventLocation() ?? $this->event->getEventLocation();

        return new Content(
            markdown: 'emails.orders.attendee-ticket',
            with: [
                'event' => $this->event,
                'attendee' => $this->attendee,
                'eventSettings' => $this->eventSettings,
                'organizer' => $this->organizer,
                'order' => $this->order,
                'occurrence' => $occurrence,
                'eventLocation' => $eventLocation,
                'effectiveVenueName' => $this->venueNameFor($eventLocation),
                'effectiveAddressString' => $this->addressStringFor($eventLocation),
                'ticketUrl' => sprintf(
                    Url::getFrontEndUrlFromConfig(Url::ATTENDEE_TICKET),
                    $this->event->getId(),
                    $this->attendee->getShortId(),
                ),
            ]
        );
    }

    private function venueNameFor(?EventLocationDomainObject $eventLocation): ?string
    {
        $venue = $this->venueLocation($eventLocation);
        if ($venue === null) {
            return null;
        }

        $name = $venue->getName();
        if ($name !== null && $name !== '') {
            return $name;
        }

        return $venue->getStructuredAddress()['venue_name'] ?? null;
    }

    private function addressStringFor(?EventLocationDomainObject $eventLocation): ?string
    {
        $venue = $this->venueLocation($eventLocation);
        if ($venue === null) {
            return null;
        }

        $address = $venue->getStructuredAddress();
        if (! is_array($address)) {
            return null;
        }

        $formatted = AddressHelper::formatAddress($address);

        return $formatted === '' ? null : $formatted;
    }

    private function venueLocation(?EventLocationDomainObject $eventLocation): ?LocationDomainObject
    {
        if ($eventLocation === null) {
            return null;
        }

        if ($eventLocation->getType() !== LocationType::IN_PERSON->name) {
            return null;
        }

        return $eventLocation->getLocation();
    }

    public function attachments(): array
    {
        $occurrence = $this->occurrence ?? null;

        $startDateRaw = $occurrence?->getStartDate() ?? $this->event->getStartDate();
        $endDateRaw = $occurrence?->getEndDate() ?? $this->event->getEndDate();

        $startDateTime = $startDateRaw ? Carbon::parse($startDateRaw, $this->event->getTimezone()) : null;
        $endDateTime = $endDateRaw ? Carbon::parse($endDateRaw, $this->event->getTimezone()) : null;

        if ($startDateTime === null) {
            return [];
        }

        $eventTitle = $this->event->getTitle();
        if ($occurrence?->getLabel()) {
            $eventTitle .= ' - '.$occurrence->getLabel();
        }

        $event = Event::create()
            ->name($eventTitle)
            ->uniqueIdentifier('event-'.$this->attendee->getId())
            ->startsAt($startDateTime)
            ->url($this->event->getEventUrl())
            ->organizer($this->organizer->getEmail(), $this->organizer->getName());

        if ($this->event->getDescription()) {
            $event->description(StringHelper::previewFromHtml($this->event->getDescription()));
        }

        $occurrence = $this->occurrence ?? $this->attendee->getEventOccurrence();
        $eventLocation = $occurrence?->getEventLocation() ?? $this->event->getEventLocation();
        $address = $this->addressStringFor($eventLocation);
        if ($address !== null) {
            $event->address($address);
        } elseif ($eventLocation?->getType() === LocationType::ONLINE->name
            && $eventLocation->getOnlineEventConnectionDetails() !== null) {
            $event->address(__('Online event'));
        }

        if ($endDateTime) {
            $event->endsAt($endDateTime);
        }

        $calendar = Calendar::create()
            ->event($event)
            ->get();

        return [
            Attachment::fromData(static fn () => $calendar, 'event.ics')
                ->withMime('text/calendar'),
        ];
    }
}
