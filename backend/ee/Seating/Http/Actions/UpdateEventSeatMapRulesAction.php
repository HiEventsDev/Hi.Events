<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\Enterprise\Seating\Resources\EventSeatMapResource;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\EventSeatMapRulesDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\UpdateEventSeatMapRulesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateEventSeatMapRulesAction extends BaseEventSeatMapAction
{
    public function __construct(
        private readonly UpdateEventSeatMapRulesHandler $handler,
    ) {}

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->authorizeEventSeatMapAccess($eventId);

        $validated = $request->validate([
            'prevent_orphan_seats' => ['required', 'boolean'],
            'max_seats_per_order' => ['present', 'nullable', 'integer', 'min:1', 'max:100'],
            'allow_seat_change' => ['required', 'boolean'],
        ]);

        return $this->resourceResponse(EventSeatMapResource::class, $this->handler->handle($eventId, new EventSeatMapRulesDTO(
            prevent_orphan_seats: (bool) $validated['prevent_orphan_seats'],
            max_seats_per_order: $validated['max_seats_per_order'] === null ? null : (int) $validated['max_seats_per_order'],
            allow_seat_change: (bool) $validated['allow_seat_change'],
        )));
    }
}
