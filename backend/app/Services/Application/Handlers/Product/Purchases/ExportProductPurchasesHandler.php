<?php

namespace HiEvents\Services\Application\Handlers\Product\Purchases;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseFilterDTO;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Services\Application\Handlers\Product\Purchases\DTO\ProductPurchaseExportDTO;

class ExportProductPurchasesHandler
{
    public function __construct(
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly ProductPurchaseProductGuard $productGuard,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(ProductPurchaseFilterDTO $filter): ProductPurchaseExportDTO
    {
        if ($filter->productId !== null) {
            $this->productGuard->assertProductBelongsToEvent($filter->eventId, $filter->productId);
        }

        return new ProductPurchaseExportDTO(
            purchases: $this->orderItemRepository->getAllProductPurchases($filter),
            timezone: $this->eventRepository->findById($filter->eventId)->getTimezone(),
        );
    }
}
