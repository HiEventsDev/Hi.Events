<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatingEventLockService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;
use Throwable;

class DetachEventSeatMapHandler
{
    public function __construct(
        private readonly EventSeatMapRepositoryInterface $eventSeatMapRepository,
        private readonly EventSeatMapLookupService $lookupService,
        private readonly SeatClaimRepositoryInterface $seatClaimRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly SeatingEventLockService $seatingEventLock,
        private readonly SeatedProductLookupService $seatedProductLookup,
        private readonly LicensedFeatureUsageService $featureUsage,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function handle(int $eventId): void
    {
        $this->databaseManager->transaction(function () use ($eventId) {
            $this->seatingEventLock->lock($eventId);

            $eventSeatMap = $this->lookupService->getForEvent($eventId);

            if ($this->seatClaimRepository->findLiveSeatsForEvent($eventId)->isNotEmpty()) {
                throw new ResourceConflictException(__('Seats are sold or held on an upcoming date, so the seat map cannot be removed'));
            }

            $this->seatClaimRepository->deleteClaimsWithoutLiveOrderForEvent($eventId);
            $this->eventSeatMapRepository->deleteById($eventSeatMap->getId());
            $this->lookupService->forget($eventId);
            $this->seatedProductLookup->forget();
        });

        $this->featureUsage->forget();
    }
}
