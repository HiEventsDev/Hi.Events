<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\SeatBlockDTO;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatBlockResultDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatBlockDatesResolver;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;
use Throwable;

class BlockSeatsHandler
{
    public function __construct(
        private readonly SeatBlockDatesResolver $datesResolver,
        private readonly SeatClaimService $seatClaimService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws SeatSelectionInvalidException
     * @throws Throwable
     */
    public function handle(SeatBlockDTO $block): SeatBlockResultDTO
    {
        $occurrenceIds = $this->datesResolver->resolve($block->event_id, $block->all_upcoming_dates, $block->event_occurrence_ids, count($block->seat_uids));

        return $this->databaseManager->transaction(
            fn () => $this->seatClaimService->block($block->event_id, $occurrenceIds, $block->seat_uids, $block->reason),
        );
    }
}
