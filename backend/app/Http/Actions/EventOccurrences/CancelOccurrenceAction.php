<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventOccurrence\CancelOccurrenceRequest;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\CancelOccurrenceHandler;
use Illuminate\Http\JsonResponse;

class CancelOccurrenceAction extends BaseAction
{
    public function __construct(
        private readonly CancelOccurrenceHandler $handler,
    )
    {
    }

    public function __invoke(int $eventId, int $occurrenceId, CancelOccurrenceRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $occurrence = $this->handler->handle(
            eventId: $eventId,
            occurrenceId: $occurrenceId,
            refundOrders: (bool) $request->input('refund_orders', false),
        );

        return $this->resourceResponse(
            resource: EventOccurrenceResource::class,
            data: $occurrence,
        );
    }
}
