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
    )
    {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $occurrences = $this->handler->handle(
            $eventId,
            QueryParamsDTO::fromArray($request->query->all()),
        );

        return $this->filterableResourceResponse(
            resource: EventOccurrenceResource::class,
            data: $occurrences,
            domainObject: EventOccurrenceDomainObject::class,
        );
    }
}
