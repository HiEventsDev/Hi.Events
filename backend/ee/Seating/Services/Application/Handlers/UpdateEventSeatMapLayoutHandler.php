<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\Enterprise\Seating\Exceptions\InvalidSeatMapLayoutException;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLayoutUpdater;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatingEventLockService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapLayoutValidator;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpdateEventSeatMapLayoutHandler
{
    public function __construct(
        private readonly EventSeatMapLookupService $lookupService,
        private readonly SeatMapLayoutValidator $layoutValidator,
        private readonly EventSeatMapLayoutUpdater $layoutUpdater,
        private readonly DatabaseManager $databaseManager,
        private readonly SeatingEventLockService $seatingEventLock,
    ) {}

    /**
     * @throws InvalidSeatMapLayoutException
     * @throws ResourceNotFoundException
     * @throws SeatMapChangeConflictException
     * @throws Throwable
     */
    public function handle(int $eventId, array $layout, ?int $expectedVersion = null, bool $confirmRelabel = false): EventSeatMapDomainObject
    {
        $validated = $this->layoutValidator->validate($layout);

        return $this->databaseManager->transaction(function () use ($eventId, $validated, $expectedVersion, $confirmRelabel) {
            $this->seatingEventLock->lock($eventId);

            $eventSeatMap = $this->lookupService->getForEvent($eventId);

            if ($expectedVersion !== null && $expectedVersion !== $eventSeatMap->getVersion()) {
                throw new SeatMapChangeConflictException(
                    __('This seat map was changed somewhere else. Reload the page to get the latest version before saving.')
                );
            }

            $this->layoutUpdater->replaceLayout($eventSeatMap, $validated->layout, allowRelabel: $confirmRelabel);

            return $this->lookupService->getForEvent($eventId);
        });
    }
}
