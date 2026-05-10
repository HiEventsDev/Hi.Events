<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetEventOccurrencesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetEventOccurrencesAction extends BaseAction
{
    public function __construct(
        private readonly GetEventOccurrencesHandler $handler,
    ) {}

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        // include_stats=false skips the per-row statistics relation for selector
        // use cases (occurrence dropdowns) where the stats payload is wasted.
        $includeStats = $request->boolean('include_stats', true);

        $occurrences = $this->handler->handle(
            $eventId,
            QueryParamsDTO::fromArray($request->query->all()),
            $includeStats,
        );

        return $this->filterableResourceResponse(
            resource: EventOccurrenceResource::class,
            data: $occurrences,
            domainObject: EventOccurrenceDomainObject::class,
        );
    }
}
