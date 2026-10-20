<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\DomainObjects\Generated\SeatMapDomainObjectAbstract;
use HiEvents\DomainObjects\SeatMapDomainObject;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Exceptions\ResourceNotFoundException;

class GetSeatMapHandler
{
    public function __construct(
        private readonly SeatMapRepositoryInterface $seatMapRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $seatMapId, int $organizerId, int $accountId): SeatMapDomainObject
    {
        return $this->seatMapRepository->findFirstWhere([
            SeatMapDomainObjectAbstract::ID => $seatMapId,
            SeatMapDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            SeatMapDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]) ?? throw new ResourceNotFoundException(__('Seat map not found'));
    }
}
