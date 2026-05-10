<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventOccurrence\UpdateProductVisibilityRequest;
use HiEvents\Resources\EventOccurrence\ProductOccurrenceVisibilityResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpdateProductVisibilityDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\UpdateProductVisibilityHandler;
use Illuminate\Http\JsonResponse;

class UpdateProductVisibilityAction extends BaseAction
{
    public function __construct(
        private readonly UpdateProductVisibilityHandler $handler,
    )
    {
    }

    public function __invoke(int $eventId, int $occurrenceId, UpdateProductVisibilityRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $visibility = $this->handler->handle(
            new UpdateProductVisibilityDTO(
                event_id: $eventId,
                event_occurrence_id: $occurrenceId,
                product_ids: $request->validated('product_ids'),
            )
        );

        return $this->resourceResponse(
            resource: ProductOccurrenceVisibilityResource::class,
            data: $visibility,
        );
    }
}
