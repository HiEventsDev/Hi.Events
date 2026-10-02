<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Resources\SeatMapDiffResource;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\SyncEventSeatMapFromSourceHandler;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncEventSeatMapFromSourceAction extends BaseEventSeatMapAction
{
    public function __construct(
        private readonly SyncEventSeatMapFromSourceHandler $handler,
    ) {}

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->authorizeEventSeatMapAccess($eventId);

        $validated = $request->validate(['dry_run' => ['required', 'boolean']]);

        try {
            $diff = $this->handler->handle($eventId, (bool) $validated['dry_run']);
        } catch (SeatMapChangeConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_CONFLICT);
        }

        return $this->resourceResponse(SeatMapDiffResource::class, $diff);
    }
}
