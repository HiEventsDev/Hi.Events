<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\EventOccurrence\ProductOccurrenceVisibilityResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetProductVisibilityHandler;
use Illuminate\Http\JsonResponse;

class GetProductVisibilityAction extends BaseAction
{
    public function __construct(
        private readonly GetProductVisibilityHandler $handler,
    )
    {
    }

    public function __invoke(int $eventId, int $occurrenceId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $visibility = $this->handler->handle($eventId, $occurrenceId);

        return $this->resourceResponse(
            resource: ProductOccurrenceVisibilityResource::class,
            data: $visibility,
        );
    }
}
