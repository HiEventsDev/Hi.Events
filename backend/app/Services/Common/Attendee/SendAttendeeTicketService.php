<?php

namespace HiEvents\Services\Common\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Mail\AttendeeTicketMail;
use Illuminate\Contracts\Mail\Mailer;

readonly class SendAttendeeTicketService
{
    public function __construct(
        private Mailer $mailer
    )
    {
    }

    public function send(AttendeeDomainObject $attendee, EventDomainObject $event): void
    {
        $this->mailer->to($attendee->getEmail())->send(new AttendeeTicketMail($attendee, $event));
    }
}
