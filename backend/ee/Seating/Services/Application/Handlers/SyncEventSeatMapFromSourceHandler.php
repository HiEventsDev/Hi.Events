<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\DomainObjects\Generated\EventSeatMapDomainObjectAbstract;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatMapDiffDTO;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapGuard;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLayoutUpdater;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatingEventLockService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapIndex;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;
use Throwable;

class SyncEventSeatMapFromSourceHandler
{
    public function __construct(
        private readonly SeatMapRepositoryInterface $seatMapRepository,
        private readonly EventSeatMapLookupService $lookupService,
        private readonly EventSeatMapGuard $guard,
        private readonly EventSeatMapLayoutUpdater $layoutUpdater,
        private readonly DatabaseManager $databaseManager,
        private readonly SeatingEventLockService $seatingEventLock,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws SeatMapChangeConflictException
     * @throws Throwable
     */
    public function handle(int $eventId, bool $dryRun): SeatMapDiffDTO
    {
        return $this->databaseManager->transaction(function () use ($eventId, $dryRun) {
            $this->seatingEventLock->lock($eventId);

            $eventSeatMap = $this->lookupService->getForEvent($eventId);
            $source = $eventSeatMap->getSeatMapId() === null
                ? null
                : $this->seatMapRepository->findFirstWhere(['id' => $eventSeatMap->getSeatMapId()]);

            if ($source === null) {
                throw new ResourceNotFoundException(__('The venue seat map this event was created from no longer exists'));
            }

            $incoming = SeatMapIndex::fromLayout($source->getLayout());
            $diff = $this->guard->diff($this->lookupService->indexFor($eventId), $incoming);

            if ($dryRun) {
                return $diff;
            }

            $this->layoutUpdater->replaceLayout($eventSeatMap, $source->getLayout(), [
                EventSeatMapDomainObjectAbstract::SOURCE_VERSION => $source->getVersion(),
            ], allowRelabel: true);

            return $diff;
        });
    }
}
