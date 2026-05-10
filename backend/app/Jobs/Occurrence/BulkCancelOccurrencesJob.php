<?php

declare(strict_types=1);

namespace HiEvents\Jobs\Occurrence;

use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Domain\Event\RecurrenceRuleExclusionService;
use HiEvents\Services\Domain\EventOccurrence\CancelOccurrenceAttendeesService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OccurrenceEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BulkCancelOccurrencesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $eventId,
        public readonly array $occurrenceIds,
        public readonly bool $refundOrders = false,
    ) {
        $this->onQueue('occurrences');
    }

    public function handle(
        EventOccurrenceRepositoryInterface $occurrenceRepository,
        RecurrenceRuleExclusionService $exclusionService,
        CancelOccurrenceAttendeesService $cancelAttendeesService,
    ): void {
        $cancelledStartDates = [];
        $failedIds = [];

        foreach ($this->occurrenceIds as $occurrenceId) {
            try {
                // Each iteration runs in its own transaction so we can lock the occurrence
                // row before checking its status. Without the lock, two concurrent bulk
                // cancellations could both observe ACTIVE and dispatch the refund /
                // notification side-effects twice.
                $cancelledStartDate = DB::transaction(function () use ($occurrenceRepository, $cancelAttendeesService, $occurrenceId) {
                    $occurrence = $occurrenceRepository->findByIdLocked($occurrenceId);

                    if (
                        ! $occurrence
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

                    $cancelAttendeesService->cancelForOccurrence($this->eventId, $occurrenceId);

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

                event(new OccurrenceEvent(
                    type: DomainEventType::OCCURRENCE_CANCELLED,
                    occurrenceId: $occurrenceId,
                ));

                $cancelledStartDates[] = $cancelledStartDate;
            } catch (\Throwable $e) {
                $failedIds[] = $occurrenceId;
                Log::error('Failed to cancel occurrence', [
                    'event_id' => $this->eventId,
                    'occurrence_id' => $occurrenceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! empty($cancelledStartDates)) {
            DB::transaction(fn () => $exclusionService->addExclusions($this->eventId, $cancelledStartDates));
        }

        $context = [
            'event_id' => $this->eventId,
            'cancelled_count' => count($cancelledStartDates),
            'failed_count' => count($failedIds),
            'failed_ids' => $failedIds,
            'refund_orders' => $this->refundOrders,
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
        ];

        if (empty($failedIds)) {
            Log::info('Bulk cancel occurrences completed', $context);
        } else {
            Log::warning('Bulk cancel occurrences completed with failures', $context);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('BulkCancelOccurrencesJob permanently failed after retries', [
            'event_id' => $this->eventId,
            'occurrence_ids' => $this->occurrenceIds,
            'refund_orders' => $this->refundOrders,
            'error' => $exception->getMessage(),
        ]);
    }
}
