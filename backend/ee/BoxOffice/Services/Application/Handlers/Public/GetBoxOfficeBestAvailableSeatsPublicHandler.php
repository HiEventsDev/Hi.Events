<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeBestAvailableSeatsDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeSeatRequestItemDTO;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\BestAvailableSeatService;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;

class GetBoxOfficeBestAvailableSeatsPublicHandler
{
    public function __construct(
        private readonly EventSeatMapLookupService $lookupService,
        private readonly SeatClaimRepositoryInterface $seatClaimRepository,
        private readonly BestAvailableSeatService $bestAvailableSeatService,
        private readonly SeatedProductLookupService $seatedProductLookup,
    ) {}

    /**
     * @return string[][] seat uids per requested item, in request order; empty when not enough seats are free
     *
     * @throws ResourceNotFoundException
     * @throws SeatSelectionInvalidException
     */
    public function handle(BoxOfficeBestAvailableSeatsDTO $request): array
    {
        $index = $this->lookupService->indexFor($request->event_id);
        $bandKeysByProduct = $this->seatedProductLookup->bandKeysByProduct($request->event_id);

        $unavailable = [
            ...$this->seatClaimRepository->findTakenSeatUids($request->event_occurrence_id),
            ...$request->excluded_seat_uids,
        ];

        $seatUids = array_fill(0, count($request->items), []);

        $groups = collect($request->items)->groupBy(
            fn (BoxOfficeSeatRequestItemDTO $item) => implode(',', $bandKeysByProduct[$item->product_id]
                ?? throw new SeatSelectionInvalidException(__('Seats are not available for this ticket'))),
            preserveKeys: true,
        );

        foreach ($groups as $bandKeys => $items) {
            $found = $this->bestAvailableSeatService->find(
                index: $index,
                bandKeys: explode(',', (string) $bandKeys),
                unavailableSeatUids: $unavailable,
                quantity: $items->sum(fn (BoxOfficeSeatRequestItemDTO $item) => $item->quantity),
            );

            if ($found === []) {
                continue;
            }

            $unavailable = [...$unavailable, ...$found];
            $offset = 0;
            foreach ($items as $position => $item) {
                $seatUids[$position] = array_slice($found, $offset, $item->quantity);
                $offset += $item->quantity;
            }
        }

        return $seatUids;
    }
}
