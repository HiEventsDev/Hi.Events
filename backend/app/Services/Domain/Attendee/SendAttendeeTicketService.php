<?php

namespace HiEvents\Services\Domain\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Attendee\AttendeeTicketMail;
use Illuminate\Contracts\Mail\Mailer;

readonly class SendAttendeeTicketService
{
    public function __construct(
        private Mailer $mailer
    )
    {
    }

    public function send(
        AttendeeDomainObject     $attendee,
        EventDomainObject        $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject    $organizer,
    ): void
    {
        $this->mailer
            ->to($attendee->getEmail())
            ->locale($attendee->getLocale())
            ->send(new AttendeeTicketMail(
                attendee: $attendee,
                event: $event,
                eventSettings: $eventSettings,
                organizer: $organizer,
            ));
    }
}
