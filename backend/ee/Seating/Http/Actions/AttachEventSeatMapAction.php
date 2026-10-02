<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\Enterprise\Seating\Resources\EventSeatMapResource;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\AttachEventSeatMapHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttachEventSeatMapAction extends BaseEventSeatMapAction
{
    public function __construct(
        private readonly AttachEventSeatMapHandler $handler,
    ) {}

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->authorizeEventSeatMapAccess($eventId);

        $validated = $request->validate(['seat_map_id' => ['required', 'integer']]);

        try {
            $eventSeatMap = $this->handler->handle($eventId, (int) $validated['seat_map_id'], $this->getAuthenticatedAccountId());
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(EventSeatMapResource::class, $eventSeatMap, ResponseCodes::HTTP_CREATED);
    }
}
