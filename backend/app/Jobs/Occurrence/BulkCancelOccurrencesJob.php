<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Occurrence;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkCancelOccurrencesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int   $eventId,
        public readonly array $occurrenceIds,
        public readonly bool  $refundOrders = false,
    ) {
    }

    public function handle(
        EventOccurrenceRepositoryInterface $occurrenceRepository,
        EventRepositoryInterface           $eventRepository,
    ): void {
        $cancelledDates = [];
        $failedIds = [];

        foreach ($this->occurrenceIds as $occurrenceId) {
            try {
                // Each iteration runs in its own transaction so we can lock the occurrence
                // row before checking its status. Without the lock, two concurrent bulk
                // cancellations could both observe ACTIVE and dispatch the refund /
                // notification side-effects twice.
                $cancelledStartDate = DB::transaction(function () use ($occurrenceRepository, $occurrenceId) {
                    $occurrence = $occurrenceRepository->findByIdLocked($occurrenceId);

                    if (
                        !$occurrence
                        || $occurrence->getEventId() !== $this->eventId
                        || $occurrence->getStatus() === EventOccurrenceStatus::CANCELLED->name
                    ) {
                        return null;
                    }

                    $occurrenceRepository->updateWhere(
                        attributes: [
                            EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::CANCELLED->name,
                        ],
                        where: [EventOccurrenceDomainObjectAbstract::ID => $occurrenceId],
                    );

                    return $occurrence->getStartDate();
                });

                if ($cancelledStartDate === null) {
                    continue;
                }

                event(new OccurrenceCancelledEvent(
                    eventId: $this->eventId,
                    occurrenceId: $occurrenceId,
                    refundOrders: $this->refundOrders,
                ));

                if ($this->refundOrders) {
                    RefundOccurrenceOrdersJob::dispatch($this->eventId, $occurrenceId);
                }

                $cancelledDates[] = date('Y-m-d', strtotime($cancelledStartDate));
            } catch (\Throwable $e) {
                $failedIds[] = $occurrenceId;
                Log::error('Failed to cancel occurrence', [
                    'event_id' => $this->eventId,
                    'occurrence_id' => $occurrenceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($cancelledDates)) {
            $this->addExcludedDates($eventRepository, $cancelledDates);
        }

        Log::info('Bulk cancel occurrences completed', [
            'event_id' => $this->eventId,
            'cancelled_count' => count($cancelledDates),
            'failed_count' => count($failedIds),
            'failed_ids' => $failedIds,
            'refund_orders' => $this->refundOrders,
        ]);
    }

    private function addExcludedDates(EventRepositoryInterface $eventRepository, array $dates): void
    {
        DB::transaction(function () use ($eventRepository, $dates) {
            $event = $eventRepository->findByIdLocked($this->eventId);

            if ($event->getType() !== EventType::RECURRING->name) {
                return;
            }

            $recurrenceRule = $event->getRecurrenceRule() ?? [];
            if (is_string($recurrenceRule)) {
                $recurrenceRule = json_decode($recurrenceRule, true, 512, JSON_THROW_ON_ERROR);
            }

            $excludedDates = $recurrenceRule['excluded_dates'] ?? [];

            foreach ($dates as $date) {
                if (!in_array($date, $excludedDates, true)) {
                    $excludedDates[] = $date;
                }
            }

            $recurrenceRule['excluded_dates'] = $excludedDates;

            $eventRepository->updateFromArray(
                id: $this->eventId,
                attributes: [
                    EventDomainObjectAbstract::RECURRENCE_RULE => $recurrenceRule,
                ],
            );
        });
    }
}
