<?php

namespace HiEvents\Services\Application\Handlers\Product\Purchases;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseFilterDTO;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseSummaryDTO;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;

class GetProductPurchaseSummaryHandler
{
    public function __construct(
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly ProductPurchaseProductGuard $productGuard,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $productId, ?int $eventOccurrenceId): ProductPurchaseSummaryDTO
    {
        $this->productGuard->assertProductBelongsToEvent($eventId, $productId);

        return $this->orderItemRepository->getProductPurchaseSummary(new ProductPurchaseFilterDTO(
            eventId: $eventId,
            productId: $productId,
            eventOccurrenceId: $eventOccurrenceId,
        ));
    }
}
