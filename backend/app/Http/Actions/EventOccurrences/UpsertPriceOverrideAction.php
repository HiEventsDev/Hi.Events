<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\InvalidOccurrenceQuantityOverrideException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventOccurrence\UpsertPriceOverrideRequest;
use HiEvents\Resources\EventOccurrence\ProductPriceOccurrenceOverrideResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\DTO\UpsertPriceOverrideDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\UpsertPriceOverrideHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpsertPriceOverrideAction extends BaseAction
{
    public function __construct(
        private readonly UpsertPriceOverrideHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(int $eventId, int $occurrenceId, UpsertPriceOverrideRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $price = $request->validated('price');
        $quantityAvailable = $request->validated('quantity_available');

        try {
            $override = $this->handler->handle(
                new UpsertPriceOverrideDTO(
                    event_id: $eventId,
                    event_occurrence_id: $occurrenceId,
                    product_price_id: $request->validated('product_price_id'),
                    price: $price === null ? null : (float) $price,
                    quantity_available: $quantityAvailable === null ? null : (int) $quantityAvailable,
                )
            );
        } catch (InvalidOccurrenceQuantityOverrideException $exception) {
            throw ValidationException::withMessages([
                'quantity_available' => $exception->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: ProductPriceOccurrenceOverrideResource::class,
            data: $override,
        );
    }
}
