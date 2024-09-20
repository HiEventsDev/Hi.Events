<?php

namespace HiEvents\Services\Handlers\Product;

use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceDomainObjectAbstract;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class DeleteProductHandler
{
    public function __construct(
        private ProductRepositoryInterface      $productRepository,
        private AttendeeRepositoryInterface     $attendeeRepository,
        private ProductPriceRepositoryInterface $productPriceRepository,
        private LoggerInterface                 $logger,
        private DatabaseManager                 $databaseManager,
    )
    {
    }

    /**
     * @throws CannotDeleteEntityException
     * @throws Throwable
     */
    public function handle(int $productId, int $eventId): void
    {
        $this->databaseManager->transaction(function () use ($productId, $eventId) {
            $this->deleteProduct($productId, $eventId);
        });
    }

    /**
     * @throws CannotDeleteEntityException
     */
    private function deleteProduct(int $productId, int $eventId): void
    {
        $attendees = $this->attendeeRepository->findWhere(
            [
                AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
                AttendeeDomainObjectAbstract::PRODUCT_ID => $productId,
            ]
        );

        if ($attendees->count() > 0) {
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

        $this->logger->info(sprintf('Product %d was deleted from event %d', $productId, $eventId), [
            'productId' => $productId,
            'eventId' => $eventId,
        ]);
    }
}
