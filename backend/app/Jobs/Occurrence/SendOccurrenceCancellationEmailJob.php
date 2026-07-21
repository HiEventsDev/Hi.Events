<?php

namespace HiEvents\Jobs\Occurrence;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
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

    private const ATTENDEE_CHUNK_SIZE = 500;

    private const DISPATCH_CHUNK_SIZE = 1000;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $eventId,
        public readonly int $occurrenceId,
        public readonly array $attendeeIds,
        public readonly bool $refundOrders = false,
    ) {
        if (config('queue.occurrences_queue_name') !== null) {
            $this->onQueue(config('queue.occurrences_queue_name'));
        }
    }

    public static function dispatchChunked(int $eventId, int $occurrenceId, array $attendeeIds, bool $refundOrders): void
    {
        foreach (array_chunk($attendeeIds, self::DISPATCH_CHUNK_SIZE) as $attendeeIdChunk) {
            self::dispatch($eventId, $occurrenceId, $attendeeIdChunk, $refundOrders);
        }
    }

    public function handle(
        EventRepositoryInterface $eventRepository,
        EventOccurrenceRepositoryInterface $occurrenceRepository,
        AttendeeRepositoryInterface $attendeeRepository,
        Mailer $mailer,
        MailBuilderService $mailBuilderService,
    ): void {
        if ($this->attendeeIds === []) {
            return;
        }

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

        $sentEmails = [];

        foreach (array_chunk($this->attendeeIds, self::ATTENDEE_CHUNK_SIZE) as $attendeeIdChunk) {
            $attendees = $attendeeRepository->findWhereIn('id', $attendeeIdChunk);

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
