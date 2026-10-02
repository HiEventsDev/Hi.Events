<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\Enterprise\Seating\Http\Request\SeatBlockRequest;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\SeatBlockDTO;

abstract class BaseSeatBlockAction extends BaseEventSeatMapAction
{
    protected function seatBlock(int $eventId, SeatBlockRequest $request): SeatBlockDTO
    {
        return new SeatBlockDTO(
            event_id: $eventId,
            all_upcoming_dates: $request->boolean('all_upcoming_dates'),
            event_occurrence_ids: array_values(array_unique(array_map('intval', $request->validated('event_occurrence_ids') ?? []))),
            seat_uids: array_values(array_unique($request->validated('seat_uids'))),
            reason: $this->plainReason($request->validated('reason')),
        );
    }

    private function plainReason(?string $reason): ?string
    {
        $plain = $reason === null ? null : strip_tags(trim($reason));

        return $plain === '' ? null : $plain;
    }
}
