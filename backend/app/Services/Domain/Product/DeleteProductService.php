<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceDomainObjectAbstract;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class DeleteProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface      $productRepository,
        private readonly ProductPriceRepositoryInterface $productPriceRepository,
        private readonly LoggerInterface                 $logger,
        private readonly DatabaseManager                 $databaseManager,
    )
    {
    }

    /**
     * @throws CannotDeleteEntityException
     * @throws Throwable
     */
    public function deleteProduct(int $productId, int $eventId): void
    {
        $this->databaseManager->transaction(function () use ($productId, $eventId) {
            if ($this->productRepository->hasAssociatedOrders($productId)) {
                throw new CannotDeleteEntityException(
                    __('You cannot delete this product because it has orders associated with it. You can hide it instead.')
                );
            }

            $this->productRepository->deleteWhere(
                [
                    ProductDomainObjectAbstract::EVENT_ID => $eventId,
                    ProductDomainObjectAbstract::ID => $productId,
                ]
            );

            $this->productPriceRepository->deleteWhere(
                [
                    ProductPriceDomainObjectAbstract::PRODUCT_ID => $productId,
                ]
            );
        });

        $this->logger->info(
            sprintf('Product with id %d was deleted from event with id %d', $productId, $eventId),
            [
                'product_id' => $productId,
                'event_id' => $eventId,
            ]
        );
    }
}
