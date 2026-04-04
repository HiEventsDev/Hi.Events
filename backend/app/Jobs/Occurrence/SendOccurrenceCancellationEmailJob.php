<?php

namespace HiEvents\Jobs\Occurrence;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Services\Domain\Email\MailBuilderService;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOccurrenceCancellationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly int  $eventId,
        private readonly int  $occurrenceId,
        private readonly bool $refundOrders = false,
    )
    {
    }

    public function handle(
        EventRepositoryInterface           $eventRepository,
        EventOccurrenceRepositoryInterface $occurrenceRepository,
        AttendeeRepositoryInterface        $attendeeRepository,
        Mailer                             $mailer,
        MailBuilderService                 $mailBuilderService,
    ): void
    {
        $occurrence = $occurrenceRepository->findById($this->occurrenceId);

        $event = $eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($this->eventId);

        $attendees = $attendeeRepository->findWhere([
            AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $this->occurrenceId,
            [AttendeeDomainObjectAbstract::STATUS, '!=', AttendeeStatus::CANCELLED->name],
        ]);

        if ($attendees->isEmpty()) {
            return;
        }

        $sentEmails = [];

        $attendees->each(function (AttendeeDomainObject $attendee) use ($mailer, $mailBuilderService, $event, $occurrence, &$sentEmails) {
            if (in_array($attendee->getEmail(), $sentEmails, true)) {
                return;
            }

            $sentEmails[] = $attendee->getEmail();

            $mail = $mailBuilderService->buildOccurrenceCancellationMail(
                event: $event,
                occurrence: $occurrence,
                organizer: $event->getOrganizer(),
                eventSettings: $event->getEventSettings(),
                refundOrders: $this->refundOrders,
            );

            $mailer
                ->to($attendee->getEmail())
                ->locale($attendee->getLocale())
                ->send($mail);
        });
    }
}
