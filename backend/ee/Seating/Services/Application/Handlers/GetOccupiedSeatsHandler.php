<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\Enterprise\Seating\Services\Domain\DTO\OccupiedSeatDTO;
use HiEvents\Enterprise\Seating\Services\Domain\OccurrenceLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatAvailabilityService;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Support\Collection;

class GetOccupiedSeatsHandler
{
    public function __construct(
        private readonly OccurrenceLookupService $occurrenceLookup,
        private readonly SeatAvailabilityService $seatAvailabilityService,
    ) {}

    /**
     * @return Collection<int, OccupiedSeatDTO>
     *
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $occurrenceId): Collection
    {
        return $this->seatAvailabilityService->occupiedSeats(
            $this->occurrenceLookup->getForEvent($eventId, $occurrenceId)->getId(),
        );
    }
}
