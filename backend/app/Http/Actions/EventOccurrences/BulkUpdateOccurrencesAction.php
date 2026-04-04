<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\Enums\BulkOccurrenceAction;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventOccurrence\BulkUpdateOccurrencesRequest;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\BulkUpdateOccurrencesHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\BulkUpdateOccurrencesDTO;
use Illuminate\Http\JsonResponse;

class BulkUpdateOccurrencesAction extends BaseAction
{
    public function __construct(
        private readonly BulkUpdateOccurrencesHandler $handler,
        private readonly EventRepositoryInterface     $eventRepository,
    )
    {
    }

    public function __invoke(int $eventId, BulkUpdateOccurrencesRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $event = $this->eventRepository->findById($eventId);

        $updatedCount = $this->handler->handle(
            new BulkUpdateOccurrencesDTO(
                event_id: $eventId,
                action: BulkOccurrenceAction::from($request->validated('action')),
                timezone: $event->getTimezone(),
                start_time_shift: $request->validated('start_time_shift') !== null
                    ? (int) $request->validated('start_time_shift')
                    : null,
                end_time_shift: $request->validated('end_time_shift') !== null
                    ? (int) $request->validated('end_time_shift')
                    : null,
                capacity: $request->validated('capacity') !== null ? (int) $request->validated('capacity') : null,
                clear_capacity: (bool) $request->validated('clear_capacity', false),
                future_only: (bool) $request->validated('future_only', true),
                skip_overridden: (bool) $request->validated('skip_overridden', true),
                refund_orders: (bool) $request->validated('refund_orders', false),
                occurrence_ids: $request->validated('occurrence_ids'),
                label: $request->validated('label'),
                clear_label: (bool) $request->validated('clear_label', false),
                duration_minutes: $request->validated('duration_minutes') !== null
                    ? (int) $request->validated('duration_minutes')
                    : null,
            )
        );

        return $this->jsonResponse([
            'updated_count' => $updatedCount,
        ]);
    }
}
