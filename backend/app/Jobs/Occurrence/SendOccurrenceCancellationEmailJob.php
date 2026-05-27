<?php

namespace HiEvents\Jobs\Occurrence;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Email\MailBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOccurrenceCancellationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly int $eventId,
        private readonly int $occurrenceId,
        private readonly bool $refundOrders = false,
    ) {
        $this->onQueue('occurrences');
    }

    public function handle(
        EventRepositoryInterface $eventRepository,
        EventOccurrenceRepositoryInterface $occurrenceRepository,
        AttendeeRepositoryInterface $attendeeRepository,
        Mailer $mailer,
        MailBuilderService $mailBuilderService,
    ): void {
        $occurrence = $occurrenceRepository
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ], name: 'event_location'))
            ->findById($this->occurrenceId);

        $event = $eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ], name: 'event_location'))
            ->findById($this->eventId);

        // Intentionally does NOT filter out CANCELLED attendees:
        // CancelOccurrenceHandler now marks attendees as CANCELLED inside the
        // same transaction that fires this job's event — a status filter here
        // would exclude the very attendees we need to notify. Anyone tied to
        // this occurrence by FK gets the cancellation email. Dedup by email
        // address below handles shared-email attendees.
        $attendees = $attendeeRepository->findWhere([
            AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID => $this->occurrenceId,
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

        Log::info('Sent occurrence cancellation emails', [
            'event_id' => $this->eventId,
            'occurrence_id' => $this->occurrenceId,
            'recipient_count' => count($sentEmails),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('SendOccurrenceCancellationEmailJob permanently failed after retries', [
            'event_id' => $this->eventId,
            'occurrence_id' => $this->occurrenceId,
            'refund_orders' => $this->refundOrders,
            'error' => $exception->getMessage(),
        ]);
    }
}
