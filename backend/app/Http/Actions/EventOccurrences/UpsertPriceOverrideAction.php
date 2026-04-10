<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventOccurrence\UpsertPriceOverrideRequest;
use HiEvents\Resources\EventOccurrence\ProductPriceOccurrenceOverrideResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\DTO\UpsertPriceOverrideDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\UpsertPriceOverrideHandler;
use Illuminate\Http\JsonResponse;

class UpsertPriceOverrideAction extends BaseAction
{
    public function __construct(
        private readonly UpsertPriceOverrideHandler $handler,
    )
    {
    }

    public function __invoke(int $eventId, int $occurrenceId, UpsertPriceOverrideRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $override = $this->handler->handle(
            new UpsertPriceOverrideDTO(
                event_id: $eventId,
                event_occurrence_id: $occurrenceId,
                product_price_id: $request->validated('product_price_id'),
                price: (float) $request->validated('price'),
            )
        );

        return $this->resourceResponse(
            resource: ProductPriceOccurrenceOverrideResource::class,
            data: $override,
        );
    }
}
