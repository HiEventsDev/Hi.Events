<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatAvailabilityDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatAvailabilityService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as Cache;

class GetSeatAvailabilityPublicHandler
{
    private const CACHE_SECONDS = 2;

    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly SeatAvailabilityService $seatAvailabilityService,
        private readonly Cache $cache,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $occurrenceId): SeatAvailabilityDTO
    {
        $occurrence = $this->occurrenceRepository->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        $availability = $occurrence === null ? null : $this->cache->remember(
            sprintf('seat_availability:%d:%d', $eventId, $occurrenceId),
            self::CACHE_SECONDS,
            fn () => $this->seatAvailabilityService->forOccurrence($eventId, $occurrenceId),
        );

        if ($availability === null) {
            throw new ResourceNotFoundException(__('Seat availability not found'));
        }

        return $availability;
    }
}
