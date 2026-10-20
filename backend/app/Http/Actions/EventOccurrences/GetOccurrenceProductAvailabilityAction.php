<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\EventOccurrence\OccurrenceProductAvailabilityResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetOccurrenceProductAvailabilityHandler;
use Illuminate\Http\JsonResponse;

class GetOccurrenceProductAvailabilityAction extends BaseAction
{
    public function __construct(
        private readonly GetOccurrenceProductAvailabilityHandler $handler,
    ) {}

    public function __invoke(int $eventId, int $occurrenceId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            resource: OccurrenceProductAvailabilityResource::class,
            data: $this->handler->handle($eventId, $occurrenceId),
        );
    }
}
