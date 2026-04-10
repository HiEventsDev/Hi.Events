<?php

namespace HiEvents\Mail\Attendee;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
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
        private readonly OrderDomainObject        $order,
        private readonly AttendeeDomainObject     $attendee,
        private readonly EventDomainObject        $event,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly OrganizerDomainObject    $organizer,
        ?RenderedEmailTemplateDTO                 $renderedTemplate = null,
        private readonly ?EventOccurrenceDomainObject $occurrence = null,
    )
    {
        parent::__construct();
        $this->renderedTemplate = $renderedTemplate;
    }

    public function envelope(): Envelope
    {
        $subject = $this->renderedTemplate?->subject ?? __('🎟️ Your Ticket for :event', [
            'event' => Str::limit($this->event->getTitle(), 50)
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

        // If no template is provided, use the default blade template
        return new Content(
            markdown: 'emails.orders.attendee-ticket',
            with: [
                'event' => $this->event,
                'attendee' => $this->attendee,
                'eventSettings' => $this->eventSettings,
                'organizer' => $this->organizer,
                'order' => $this->order,
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
        $startDateRaw = $this->occurrence?->getStartDate() ?? $this->event->getStartDate();
        $endDateRaw = $this->occurrence?->getEndDate() ?? $this->event->getEndDate();

        $startDateTime = $startDateRaw ? Carbon::parse($startDateRaw, $this->event->getTimezone()) : null;
        $endDateTime = $endDateRaw ? Carbon::parse($endDateRaw, $this->event->getTimezone()) : null;

        if ($startDateTime === null) {
            return [];
        }

        $eventTitle = $this->event->getTitle();
        if ($this->occurrence?->getLabel()) {
            $eventTitle .= ' - ' . $this->occurrence->getLabel();
        }

        $event = Event::create()
            ->name($eventTitle)
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
