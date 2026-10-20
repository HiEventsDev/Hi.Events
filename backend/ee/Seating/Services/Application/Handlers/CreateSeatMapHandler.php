<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\DomainObjects\Generated\SeatMapDomainObjectAbstract;
use HiEvents\DomainObjects\SeatMapDomainObject;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Enterprise\Seating\Exceptions\InvalidSeatMapLayoutException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\UpsertSeatMapDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapLayoutValidator;

class CreateSeatMapHandler
{
    public function __construct(
        private readonly SeatMapRepositoryInterface $seatMapRepository,
        private readonly SeatMapLayoutValidator $layoutValidator,
        private readonly LicensedFeatureUsageService $featureUsage,
    ) {}

    /**
     * @throws InvalidSeatMapLayoutException
     */
    public function handle(UpsertSeatMapDTO $dto): SeatMapDomainObject
    {
        $validated = $this->layoutValidator->validate($dto->layout);

        $seatMap = $this->seatMapRepository->create([
            SeatMapDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            SeatMapDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            SeatMapDomainObjectAbstract::NAME => trim(strip_tags($dto->name)),
            SeatMapDomainObjectAbstract::LAYOUT => $validated->layout,
            SeatMapDomainObjectAbstract::SEAT_COUNT => $validated->seat_count,
        ]);

        $this->featureUsage->forget();

        return $seatMap;
    }
}
