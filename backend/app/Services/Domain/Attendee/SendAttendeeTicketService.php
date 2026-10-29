<?php

namespace HiEvents\Services\Domain\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Services\Domain\Email\MailBuilderService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Collection;

class SendAttendeeTicketService
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly MailBuilderService $mailBuilderService,
    ) {}

    public function send(
        OrderDomainObject $order,
        AttendeeDomainObject $attendee,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
    ): void {
        if ($attendee->getEmail() === null) {
            return;
        }

        $mail = $this->mailBuilderService->buildAttendeeTicketMail(
            $attendee,
            $order,
            $event,
            $eventSettings,
            $organizer,
            $attendee->getEventOccurrence(),
        );

        $this->mailer
            ->to($attendee->getEmail())
            ->locale($attendee->getLocale())
            ->send($mail);
    }

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     */
    public function sendTickets(
        OrderDomainObject $order,
        Collection $attendees,
        EventDomainObject $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject $organizer,
    ): void {
        $recipient = $attendees->first();
        if ($recipient?->getEmail() === null) {
            return;
        }

        $mails = $this->mailBuilderService->buildAttendeeTicketMails(
            $attendees,
            $order,
            $event,
            $eventSettings,
            $organizer,
        );

        foreach ($mails as $mail) {
            $this->mailer
                ->to($recipient->getEmail())
                ->locale($recipient->getLocale())
                ->send($mail);
        }
    }
}
