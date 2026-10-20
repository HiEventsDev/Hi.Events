<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\Public;

use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BestAvailableSeatsRequestDTO;
use HiEvents\Enterprise\Seating\Services\Domain\BestAvailableSeatService;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;

class GetBestAvailableSeatsPublicHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly EventSeatMapLookupService $lookupService,
        private readonly SeatClaimRepositoryInterface $seatClaimRepository,
        private readonly BestAvailableSeatService $bestAvailableSeatService,
        private readonly SeatedProductLookupService $seatedProductLookup,
    ) {}

    /**
     * @return string[]
     *
     * @throws ResourceNotFoundException
     * @throws SeatSelectionInvalidException
     */
    public function handle(BestAvailableSeatsRequestDTO $request): array
    {
        $occurrence = $this->occurrenceRepository->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $request->event_occurrence_id,
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $request->event_id,
        ]);
        $eventSeatMap = $this->lookupService->getForEvent($request->event_id);
        $bandKeys = $this->seatedProductLookup->bandKeysByProduct($request->event_id)[$request->product_id] ?? [];

        if ($occurrence === null || $bandKeys === []) {
            throw new ResourceNotFoundException(__('Seats are not available for this ticket'));
        }

        $maxSeatsPerOrder = $eventSeatMap->getMaxSeatsPerOrder();

        if ($maxSeatsPerOrder !== null && $request->quantity > $maxSeatsPerOrder) {
            throw new SeatSelectionInvalidException(__('You can choose at most :count seats per order', [
                'count' => $maxSeatsPerOrder,
            ]));
        }

        return $this->bestAvailableSeatService->find(
            index: $this->lookupService->indexFor($request->event_id),
            bandKeys: $bandKeys,
            unavailableSeatUids: [
                ...$this->seatClaimRepository->findTakenSeatUids($request->event_occurrence_id),
                ...$request->excluded_seat_uids,
            ],
            quantity: $request->quantity,
            accessibleOnly: $request->accessible,
            preventOrphans: (bool) $eventSeatMap->getPreventOrphanSeats(),
        );
    }
}
