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
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use Illuminate\Http\JsonResponse;

class UpdateEventOccurrenceAction extends BaseAction
{
    public function __construct(
        private readonly UpdateEventOccurrenceHandler $handler,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function __invoke(int $eventId, int $occurrenceId, UpsertEventOccurrenceRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $event = $this->eventRepository->findById($eventId);
        $timezone = $event->getTimezone();

        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');
        $eventLocationPayload = $request->validated('event_location');

        $occurrence = $this->handler->handle(
            $occurrenceId,
            new UpsertEventOccurrenceDTO(
                event_id: $eventId,
                start_date: DateHelper::convertToUTC($startDate, $timezone),
                end_date: $endDate ? DateHelper::convertToUTC($endDate, $timezone) : null,
                capacity: $request->validated('capacity'),
                label: $request->validated('label'),
                show_available_capacity: $request->validated('show_available_capacity'),
                event_location: $eventLocationPayload !== null ? EventLocationData::fromArray($eventLocationPayload) : null,
                clear_event_location: (bool) $request->validated('clear_event_location', false),
            )
        );

        return $this->resourceResponse(
            resource: EventOccurrenceResource::class,
            data: $occurrence,
        );
    }
}
