<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\Seating\Resources\EventSeatMapResource;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\GetEventSeatMapHandler;
use Illuminate\Http\JsonResponse;

class GetEventSeatMapAction extends BaseSeatingAction
{
    public function __construct(
        private readonly GetEventSeatMapHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(EventSeatMapResource::class, $this->handler->handle($eventId));
    }
}
