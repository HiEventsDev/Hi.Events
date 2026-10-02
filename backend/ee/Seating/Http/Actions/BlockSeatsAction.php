<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Http\Request\SeatBlockRequest;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\BlockSeatsHandler;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SkippedSeatBlockDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BlockSeatsAction extends BaseSeatBlockAction
{
    public function __construct(
        private readonly BlockSeatsHandler $handler,
    ) {}

    public function __invoke(int $eventId, SeatBlockRequest $request): JsonResponse
    {
        $this->authorizeEventSeatMapAccess($eventId);

        try {
            $result = $this->handler->handle($this->seatBlock($eventId, $request));
        } catch (SeatSelectionInvalidException $exception) {
            throw ValidationException::withMessages(['seat_uids' => $exception->getMessage()]);
        }

        return $this->jsonResponse([
            'blocked' => $result->blocked,
            'skipped' => array_map(static fn (SkippedSeatBlockDTO $skipped) => [
                'event_occurrence_id' => $skipped->event_occurrence_id,
                'seat_uids' => $skipped->seat_uids,
            ], $result->skipped),
        ], wrapInData: true);
    }
}
