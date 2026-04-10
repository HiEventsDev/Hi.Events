<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventOccurrence\GenerateOccurrencesRequest;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\GenerateOccurrencesFromRuleHandler;
use Illuminate\Http\JsonResponse;

class GenerateOccurrencesAction extends BaseAction
{
    public function __construct(
        private readonly GenerateOccurrencesFromRuleHandler $handler,
    )
    {
    }

    public function __invoke(int $eventId, GenerateOccurrencesRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $occurrences = $this->handler->handle(
            new GenerateOccurrencesDTO(
                event_id: $eventId,
                recurrence_rule: $request->validated('recurrence_rule'),
            )
        );

        return $this->resourceResponse(
            resource: EventOccurrenceResource::class,
            data: $occurrences,
        );
    }
}
