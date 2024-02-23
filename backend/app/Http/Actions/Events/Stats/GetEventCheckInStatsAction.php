<?php

namespace TicketKitten\Http\Actions\Events\Stats;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Service\Handler\Event\GetEventCheckInStatsHandler;

class GetEventCheckInStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetEventCheckInStatsHandler $eventStatsHandler
    )
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(JsonResource::class, $this->eventStatsHandler->handle($eventId));
    }
}
