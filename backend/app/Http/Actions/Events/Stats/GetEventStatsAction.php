<?php

namespace HiEvents\Http\Actions\Events\Stats;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\EventStatsRequestDTO;
use HiEvents\Service\Handler\Event\GetEventStatsHandler;

class GetEventStatsAction extends BaseAction
{
    public function __construct(
        private readonly GetEventStatsHandler $eventStatsHandler
    )
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $stats = $this->eventStatsHandler->handle(EventStatsRequestDTO::fromArray([
            'event_id' => $eventId,
            'start_date' => Carbon::now()->subDays(7)->format('Y-m-d H:i:s'),
            'end_date' => Carbon::now()->format('Y-m-d H:i:s')
        ]));

        return $this->resourceResponse(JsonResource::class, $stats);
    }
}
