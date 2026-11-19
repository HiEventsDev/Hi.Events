<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Jobs;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SeedContactSyncJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const PHASE_ORDERS = 'orders';

    public const PHASE_WAITLIST = 'waitlist';

    public const PHASE_REMOVED_ORDERS = 'removed_orders';

    public const PHASE_REMOVED_WAITLIST = 'removed_waitlist';

    private const EVENT_PHASES = [self::PHASE_ORDERS, self::PHASE_WAITLIST];

    private const ORDER_PHASES = [self::PHASE_ORDERS, self::PHASE_REMOVED_ORDERS];

    private const CHUNK_SIZE = 500;

    private const MAX_CHUNKS_PER_RUN = 40;

    private const MAX_EVENTS_PER_RUN = 200;

    private const EVENT_PAGE_SIZE = 100;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 40;

    /**
     * @var object[]
     */
    private array $jobs = [];

    /**
     * @var int[]
     */
    private array $buffer = [];

    /**
     * @var int[]
     */
    private array $upcomingEventIds = [];

    private string $removedPhase = self::PHASE_REMOVED_ORDERS;

    private int $removedAfterId = 0;

    public function __construct(
        public int $accountId,
        public int $organizerId,
        public string $phase = self::PHASE_ORDERS,
        public int $eventId = 0,
        public int $afterId = 0,
    ) {}

    public function handle(CrmProjectionRepositoryInterface $projectionRepository): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $phase = $this->phase;
        $afterId = $this->afterId;
        $eventId = null;
        $eventsVisited = 0;

        if (in_array($phase, self::EVENT_PHASES, true)) {
            $eventId = $this->eventId === 0 ? $this->nextEventId($projectionRepository, 0) : $this->eventId;
        }

        while ($eventId !== null && count($this->jobs) < self::MAX_CHUNKS_PER_RUN && $eventsVisited < self::MAX_EVENTS_PER_RUN) {
            $ids = $phase === self::PHASE_ORDERS
                ? $projectionRepository->findOrderIdsForEvent($eventId, $afterId, self::CHUNK_SIZE)
                : $projectionRepository->findWaitlistEntryIdsForEvent($eventId, $afterId, self::CHUNK_SIZE);

            $this->collect($phase, $ids);

            if (count($ids) === self::CHUNK_SIZE) {
                $afterId = end($ids);

                continue;
            }

            $afterId = 0;
            $eventsVisited++;
            $eventId = $this->nextEventId($projectionRepository, $eventId);

            if ($eventId === null && $phase === self::PHASE_ORDERS) {
                $this->flush($phase);
                $phase = self::PHASE_WAITLIST;
                $eventId = $this->nextEventId($projectionRepository, 0);
            }
        }

        $this->flush($phase);

        if ($eventId !== null) {
            $this->jobs[] = new self($this->accountId, $this->organizerId, $phase, $eventId, $afterId);
        } elseif (! $this->collectRemoved($projectionRepository, $phase, $afterId)) {
            $this->jobs[] = new self($this->accountId, $this->organizerId, $this->removedPhase, 0, $this->removedAfterId);
        }

        if ($this->jobs !== []) {
            $this->batch()?->add($this->jobs);
        }
    }

    private function collectRemoved(CrmProjectionRepositoryInterface $projectionRepository, string $phase, int $afterId): bool
    {
        $this->removedPhase = in_array($phase, self::EVENT_PHASES, true) ? self::PHASE_REMOVED_ORDERS : $phase;
        $this->removedAfterId = in_array($phase, self::EVENT_PHASES, true) ? 0 : $afterId;

        while (count($this->jobs) < self::MAX_CHUNKS_PER_RUN) {
            $ids = $this->removedPhase === self::PHASE_REMOVED_ORDERS
                ? $projectionRepository->findProjectedOrderIdsNoLongerLive($this->organizerId, $this->removedAfterId, self::CHUNK_SIZE)
                : $projectionRepository->findProjectedWaitlistEntryIdsNoLongerLive($this->organizerId, $this->removedAfterId, self::CHUNK_SIZE);

            $this->collect($this->removedPhase, $ids);

            if (count($ids) === self::CHUNK_SIZE) {
                $this->removedAfterId = end($ids);

                continue;
            }

            $this->flush($this->removedPhase);

            if ($this->removedPhase === self::PHASE_REMOVED_WAITLIST) {
                return true;
            }

            $this->removedPhase = self::PHASE_REMOVED_WAITLIST;
            $this->removedAfterId = 0;
        }

        return false;
    }

    private function nextEventId(CrmProjectionRepositoryInterface $projectionRepository, int $afterEventId): ?int
    {
        if ($this->upcomingEventIds === [] || $this->upcomingEventIds[0] <= $afterEventId) {
            $this->upcomingEventIds = $projectionRepository->findEventIdsForOrganizer($this->organizerId, $afterEventId, self::EVENT_PAGE_SIZE);
        }

        return array_shift($this->upcomingEventIds);
    }

    /**
     * @param  int[]  $ids
     */
    private function collect(string $phase, array $ids): void
    {
        array_push($this->buffer, ...$ids);

        while (count($this->buffer) >= self::CHUNK_SIZE) {
            $this->jobs[] = $this->makeJob($phase, array_splice($this->buffer, 0, self::CHUNK_SIZE));
        }
    }

    private function flush(string $phase): void
    {
        if ($this->buffer !== []) {
            $this->jobs[] = $this->makeJob($phase, $this->buffer);
            $this->buffer = [];
        }
    }

    /**
     * @param  int[]  $ids
     */
    private function makeJob(string $phase, array $ids): object
    {
        return in_array($phase, self::ORDER_PHASES, true)
            ? new ProjectOrdersJob($this->accountId, $this->organizerId, $ids)
            : new ProjectWaitlistEntriesJob($this->accountId, $this->organizerId, $ids);
    }
}
