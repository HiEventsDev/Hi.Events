<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetEventOccurrenceHandler;
use Illuminate\Http\JsonResponse;

class GetEventOccurrenceAction extends BaseAction
{
    public function __construct(
        private readonly GetEventOccurrenceHandler $handler,
    )
    {
    }

    public function __invoke(int $eventId, int $occurrenceId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $occurrence = $this->handler->handle($eventId, $occurrenceId);

        return $this->resourceResponse(
            resource: EventOccurrenceResource::class,
            data: $occurrence,
        );
    }
}
