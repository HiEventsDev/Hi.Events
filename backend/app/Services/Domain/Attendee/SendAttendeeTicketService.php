<?php

namespace HiEvents\Services\Domain\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\TransactionalEmailType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Services\Domain\Email\MailBuilderService;
use HiEvents\Services\Domain\Email\TransactionalEmailTrackingService;
use Illuminate\Contracts\Mail\Mailer;

class SendAttendeeTicketService
{
    public function __construct(
        private readonly Mailer                             $mailer,
        private readonly MailBuilderService                 $mailBuilderService,
        private readonly TransactionalEmailTrackingService  $trackingService,
    )
    {
    }

    public function send(
        OrderDomainObject        $order,
        AttendeeDomainObject     $attendee,
        EventDomainObject        $event,
        EventSettingDomainObject $eventSettings,
        OrganizerDomainObject    $organizer,
        ?string                  $retryForSesMessageId = null,
        ?int                     $retryForId = null,
    ): void
    {
        $mail = $this->mailBuilderService->buildAttendeeTicketMail(
            $attendee,
            $order,
            $event,
            $eventSettings,
            $organizer
        );

        $this->trackingService->recordAndSend(
            mailer: $this->mailer,
            recipient: $attendee->getEmail(),
            mail: $mail,
            emailType: TransactionalEmailType::ATTENDEE_TICKET,
            subject: $mail->envelope()->subject,
            eventId: $event->getId(),
            orderId: $order->getId(),
            attendeeId: $attendee->getId(),
            accountId: $event->getAccountId(),
            locale: $attendee->getLocale(),
            retryForSesMessageId: $retryForSesMessageId,
            retryForId: $retryForId,
        );
    }
}
