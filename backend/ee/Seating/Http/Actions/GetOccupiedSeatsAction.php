<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\Seating\Resources\OccupiedSeatResource;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\GetOccupiedSeatsHandler;
use Illuminate\Http\JsonResponse;

class GetOccupiedSeatsAction extends BaseSeatingAction
{
    public function __construct(
        private readonly GetOccupiedSeatsHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $occurrenceId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(OccupiedSeatResource::class, $this->handler->handle($eventId, $occurrenceId));
    }
}
