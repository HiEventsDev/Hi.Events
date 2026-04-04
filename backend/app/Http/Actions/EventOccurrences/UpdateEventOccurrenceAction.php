<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Helper\DateHelper;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventOccurrence\UpsertEventOccurrenceRequest;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\UpdateEventOccurrenceHandler;
use Illuminate\Http\JsonResponse;

class UpdateEventOccurrenceAction extends BaseAction
{
    public function __construct(
        private readonly UpdateEventOccurrenceHandler $handler,
        private readonly EventRepositoryInterface     $eventRepository,
    )
    {
    }

    public function __invoke(int $eventId, int $occurrenceId, UpsertEventOccurrenceRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $event = $this->eventRepository->findById($eventId);
        $timezone = $event->getTimezone();

        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $occurrence = $this->handler->handle(
            $occurrenceId,
            new UpsertEventOccurrenceDTO(
                event_id: $eventId,
                start_date: DateHelper::convertToUTC($startDate, $timezone),
                end_date: $endDate ? DateHelper::convertToUTC($endDate, $timezone) : null,
                status: $request->validated('status'),
                capacity: $request->validated('capacity'),
                label: $request->validated('label'),
            )
        );

        return $this->resourceResponse(
            resource: EventOccurrenceResource::class,
            data: $occurrence,
        );
    }
}
