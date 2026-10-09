<?php

namespace HiEvents\Http\Actions\Products\Purchases;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Product\GetProductPurchaseSummaryRequest;
use HiEvents\Resources\Product\ProductPurchaseSummaryResource;
use HiEvents\Services\Application\Handlers\Product\Purchases\GetProductPurchaseSummaryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GetProductPurchaseSummaryAction extends BaseAction
{
    public function __construct(
        private readonly GetProductPurchaseSummaryHandler $handler,
    ) {}

    public function __invoke(GetProductPurchaseSummaryRequest $request, int $eventId, int $productId): JsonResponse|Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $eventOccurrenceId = $request->validated('event_occurrence_id');

        try {
            $summary = $this->handler->handle(
                eventId: $eventId,
                productId: $productId,
                eventOccurrenceId: $eventOccurrenceId !== null ? (int) $eventOccurrenceId : null,
            );
        } catch (ResourceNotFoundException) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(ProductPurchaseSummaryResource::class, $summary);
    }
}
