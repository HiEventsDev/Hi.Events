<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\EventOccurrence\ProductPriceOccurrenceOverrideResource;
use HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride\GetPriceOverridesHandler;
use Illuminate\Http\JsonResponse;

class GetPriceOverridesAction extends BaseAction
{
    public function __construct(
        private readonly GetPriceOverridesHandler $handler,
    )
    {
    }

    public function __invoke(int $eventId, int $occurrenceId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $overrides = $this->handler->handle($eventId, $occurrenceId);

        return $this->resourceResponse(
            resource: ProductPriceOccurrenceOverrideResource::class,
            data: $overrides,
        );
    }
}
