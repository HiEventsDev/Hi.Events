<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Helper\DateHelper;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventOccurrence\UpsertEventOccurrenceRequest;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\CreateEventOccurrenceHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use Illuminate\Http\JsonResponse;

class CreateEventOccurrenceAction extends BaseAction
{
    public function __construct(
        private readonly CreateEventOccurrenceHandler $handler,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function __invoke(int $eventId, UpsertEventOccurrenceRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $event = $this->eventRepository->findById($eventId);
        $timezone = $event->getTimezone();

        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');
        $eventLocationPayload = $request->validated('event_location');

        $occurrence = $this->handler->handle(
            new UpsertEventOccurrenceDTO(
                event_id: $eventId,
                start_date: DateHelper::convertToUTC($startDate, $timezone),
                end_date: $endDate ? DateHelper::convertToUTC($endDate, $timezone) : null,
                capacity: $request->validated('capacity'),
                label: $request->validated('label'),
                show_available_capacity: $request->validated('show_available_capacity'),
                is_overridden: true,
                event_location: $eventLocationPayload !== null ? EventLocationData::fromArray($eventLocationPayload) : null,
            )
        );

        return $this->resourceResponse(
            resource: EventOccurrenceResource::class,
            data: $occurrence,
        );
    }
}
