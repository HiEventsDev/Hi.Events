<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\SeatBlockDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatBlockDatesResolver;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Exceptions\ResourceNotFoundException;

class ReleaseSeatBlocksHandler
{
    public function __construct(
        private readonly SeatBlockDatesResolver $datesResolver,
        private readonly SeatClaimService $seatClaimService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws SeatSelectionInvalidException
     */
    public function handle(SeatBlockDTO $block): int
    {
        return $this->seatClaimService->unblock(
            $this->datesResolver->resolve($block->event_id, $block->all_upcoming_dates, $block->event_occurrence_ids, count($block->seat_uids)),
            $block->seat_uids,
        );
    }
}
