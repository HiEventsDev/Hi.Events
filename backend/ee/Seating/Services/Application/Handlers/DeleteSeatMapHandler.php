<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Exceptions\ResourceNotFoundException;

class DeleteSeatMapHandler
{
    public function __construct(
        private readonly SeatMapRepositoryInterface $seatMapRepository,
        private readonly GetSeatMapHandler $getSeatMapHandler,
        private readonly LicensedFeatureUsageService $featureUsage,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $seatMapId, int $organizerId, int $accountId): void
    {
        $seatMap = $this->getSeatMapHandler->handle($seatMapId, $organizerId, $accountId);

        $this->seatMapRepository->deleteById($seatMap->getId());
        $this->featureUsage->forget();
    }
}
