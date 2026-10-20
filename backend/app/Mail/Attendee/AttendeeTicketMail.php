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
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\Concerns\ProvidesEventContent;
use HiEvents\Services\Domain\Email\DTO\RenderedEmailTemplateDTO;
use HiEvents\Services\Domain\Lineup\DTO\EmailLineupHighlightsDTO;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

/**
 * @uses /backend/resources/views/emails/orders/attendee-ticket.blade.php
 */
class AttendeeTicketMail extends BaseMail implements ContainsEventContent
{
    use ProvidesEventContent;

    private readonly ?RenderedEmailTemplateDTO $renderedTemplate;

    private ?EmailLineupHighlightsDTO $lineup = null;

    public function __construct(
        private readonly OrderDomainObject $order,
        private readonly AttendeeDomainObject $attendee,
        private readonly EventDomainObject $event,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly OrganizerDomainObject $organizer,
        ?RenderedEmailTemplateDTO $renderedTemplate = null,
        private readonly ?EventOccurrenceDomainObject $occurrence = null,
        private readonly ?Collection $attendees = null,
        ?EmailLineupHighlightsDTO $lineup = null,
    ) {
        parent::__construct();
        $this->renderedTemplate = $renderedTemplate;
        $this->lineup = $lineup;
    }

    public function envelope(): Envelope
    {
        $replacements = ['event' => Str::limit($this->event->getTitle(), 50)];
        $subject = $this->renderedTemplate?->subject ?? ($this->tickets()->count() > 1
            ? __('🎟️ Your Tickets for :event', $replacements)
            : __('🎟️ Your Ticket for :event', $replacements));

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

        return new Content(
            markdown: 'emails.orders.attendee-ticket',
            with: [
                'event' => $this->event,
                'eventSettings' => $this->eventSettings,
                'organizer' => $this->organizer,
                'order' => $this->order,
                'lineup' => $this->lineup,
                'tickets' => $this->tickets()
                    ->map(fn (AttendeeDomainObject $attendee) => $this->ticketViewData($attendee))
                    ->all(),
            ]
        );
    }

    /**
     * @return Collection<int, AttendeeDomainObject>
     */
    private function tickets(): Collection
    {
        return ($this->attendees ?? null) ?? collect([$this->attendee]);
    }

    private function occurrenceFor(AttendeeDomainObject $attendee): ?EventOccurrenceDomainObject
    {
        if ($this->tickets()->count() > 1) {
            return $attendee->getEventOccurrence();
        }

        return $this->occurrence ?? $attendee->getEventOccurrence();
    }

    private function ticketViewData(AttendeeDomainObject $attendee): array
    {
        $occurrence = $this->occurrenceFor($attendee);
        $eventLocation = $occurrence?->getEventLocation() ?? $this->event->getEventLocation();

        return [
            'attendee' => $attendee,
            'occurrence' => $occurrence,
            'startDate' => $this->startDateFor($occurrence),
            'endDate' => $this->endDateFor($occurrence),
            'venueName' => $this->venueNameFor($eventLocation),
            'addressString' => $this->addressStringFor($eventLocation),
            'ticketUrl' => sprintf(
                Url::getFrontEndUrlFromConfig(Url::ATTENDEE_TICKET),
                $this->event->getId(),
                $attendee->getShortId(),
            ),
        ];
    }

    private function startDateFor(?EventOccurrenceDomainObject $occurrence): ?string
    {
        return $occurrence?->getStartDate() ?? $this->event->getStartDate();
    }

    private function endDateFor(?EventOccurrenceDomainObject $occurrence): ?string
    {
        $start = $this->startDateFor($occurrence);
        $end = $occurrence === null ? $this->event->getEndDate() : $occurrence->getEndDate();

        if ($start === null || $end === null || ! Carbon::parse($end)->greaterThan(Carbon::parse($start))) {
            return null;
        }

        return $end;
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
        $calendarEvents = $this->tickets()
            ->groupBy(fn (AttendeeDomainObject $attendee) => $this->occurrenceFor($attendee)?->getId() ?? 0)
            ->map(fn (Collection $attendees) => $this->calendarEventFor($attendees->first()))
            ->filter()
            ->values()
            ->all();

        if ($calendarEvents === []) {
            return [];
        }

        $calendar = Calendar::create()
            ->event($calendarEvents)
            ->get();

        return [
            Attachment::fromData(static fn () => $calendar, 'event.ics')
                ->withMime('text/calendar'),
        ];
    }

    private function calendarEventFor(AttendeeDomainObject $attendee): ?Event
    {
        $occurrence = $this->occurrenceFor($attendee);

        $startDateRaw = $this->startDateFor($occurrence);
        $endDateRaw = $this->endDateFor($occurrence);

        $startDateTime = $startDateRaw ? Carbon::parse($startDateRaw, $this->event->getTimezone()) : null;
        $endDateTime = $endDateRaw ? Carbon::parse($endDateRaw, $this->event->getTimezone()) : null;

        if ($startDateTime === null) {
            return null;
        }

        $eventTitle = $this->event->getTitle();
        if ($occurrence?->getLabel()) {
            $eventTitle .= ' - '.$occurrence->getLabel();
        }

        $event = Event::create()
            ->name($eventTitle)
            ->uniqueIdentifier('event-'.$attendee->getId())
            ->startsAt($startDateTime)
            ->url($this->event->getEventUrl())
            ->organizer($this->organizer->getEmail(), $this->organizer->getName());

        if ($this->event->getDescription()) {
            $event->description(StringHelper::previewFromHtml($this->event->getDescription()));
        }

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

        return $event;
    }
}
