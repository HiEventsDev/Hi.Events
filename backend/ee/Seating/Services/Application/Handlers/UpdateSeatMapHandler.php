<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\DomainObjects\Generated\SeatMapDomainObjectAbstract;
use HiEvents\DomainObjects\SeatMapDomainObject;
use HiEvents\Enterprise\Seating\Exceptions\InvalidSeatMapLayoutException;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\UpsertSeatMapDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapLayoutValidator;
use HiEvents\Exceptions\ResourceNotFoundException;

class UpdateSeatMapHandler
{
    public function __construct(
        private readonly SeatMapRepositoryInterface $seatMapRepository,
        private readonly SeatMapLayoutValidator $layoutValidator,
        private readonly GetSeatMapHandler $getSeatMapHandler,
    ) {}

    /**
     * @throws InvalidSeatMapLayoutException
     * @throws ResourceNotFoundException
     * @throws SeatMapChangeConflictException
     */
    public function handle(int $seatMapId, UpsertSeatMapDTO $dto): SeatMapDomainObject
    {
        $seatMap = $this->getSeatMapHandler->handle($seatMapId, $dto->organizer_id, $dto->account_id);

        if ($dto->version !== null && $dto->version !== $seatMap->getVersion()) {
            throw new SeatMapChangeConflictException(
                __('This seat map was changed somewhere else. Reload the page to get the latest version before saving.')
            );
        }

        $validated = $this->layoutValidator->validate($dto->layout);

        $updated = $this->seatMapRepository->updateWhere([
            SeatMapDomainObjectAbstract::NAME => trim(strip_tags($dto->name)),
            SeatMapDomainObjectAbstract::LAYOUT => json_encode($validated->layout, JSON_THROW_ON_ERROR),
            SeatMapDomainObjectAbstract::SEAT_COUNT => $validated->seat_count,
            SeatMapDomainObjectAbstract::VERSION => $seatMap->getVersion() + 1,
        ], [
            SeatMapDomainObjectAbstract::ID => $seatMap->getId(),
            SeatMapDomainObjectAbstract::VERSION => $seatMap->getVersion(),
        ]);

        if ($updated === 0) {
            throw new SeatMapChangeConflictException(
                __('This seat map was changed somewhere else. Reload the page to get the latest version before saving.')
            );
        }

        return $this->seatMapRepository->findById($seatMap->getId());
    }
}
