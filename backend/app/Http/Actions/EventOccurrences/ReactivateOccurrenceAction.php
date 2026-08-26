<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventOccurrence\ReactivateOccurrenceRequest;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\ReactivateOccurrenceHandler;
use Illuminate\Http\JsonResponse;

class ReactivateOccurrenceAction extends BaseAction
{
    public function __construct(
        private readonly ReactivateOccurrenceHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $occurrenceId, ReactivateOccurrenceRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $occurrence = $this->handler->handle(
            eventId: $eventId,
            occurrenceId: $occurrenceId,
        );

        return $this->resourceResponse(
            resource: EventOccurrenceResource::class,
            data: $occurrence,
        );
    }
}
