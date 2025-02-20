<?php

namespace HiEvents\Services\Domain\ProductCategory;

use HiEvents\DomainObjects\Generated\ProductCategoryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\DeleteProductService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

class DeleteProductCategoryService
{
    public function __construct(
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
        private readonly ProductRepositoryInterface         $productRepository,
        private readonly DeleteProductService               $deleteProductService,
        private readonly LoggerInterface                    $logger,
        private readonly DatabaseManager                    $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     * @throws CannotDeleteEntityException
     */
    public function deleteProductCategory(int $productCategoryId, int $eventId): void
    {
        $this->databaseManager->transaction(function () use ($productCategoryId, $eventId) {
            $this->handleDeletion($productCategoryId, $eventId);
        });
    }

    /**
     * @throws Throwable
     * @throws CannotDeleteEntityException
     */
    private function handleDeletion(int $productCategoryId, int $eventId): void
    {
        $this->validateCanDeleteProductCategory($eventId);

        $this->deleteCategoryProducts($productCategoryId, $eventId);

        $this->deleteCategory($productCategoryId, $eventId);
    }

    /**
     * @throws CannotDeleteEntityException
     * @throws Throwable
     */
    private function deleteCategoryProducts(int $productCategoryId, int $eventId): void
    {
        $productsToDelete = $this->productRepository->findWhere(
            [
                ProductDomainObjectAbstract::PRODUCT_CATEGORY_ID => $productCategoryId,
                ProductDomainObjectAbstract::EVENT_ID => $eventId,
            ]
        );

        $productsWhichCanNotBeDeleted = new Collection();

        foreach ($productsToDelete as $product) {
            try {
                $this->deleteProductService->deleteProduct($product->getId(), $eventId);
            } catch (CannotDeleteEntityException) {
                $productsWhichCanNotBeDeleted->push($product);
            }
        }

        if ($productsWhichCanNotBeDeleted->isNotEmpty()) {
            throw new CannotDeleteEntityException(
                __('You cannot delete this product category because it contains the following products: :products. These products are linked to existing orders. Please move the :product_name to another category before attempting to delete this one.', [
                    'products' => $productsWhichCanNotBeDeleted->map(fn($product) => $product->getTitle())->implode(', '),
                    'product_name' => $productsWhichCanNotBeDeleted->count() > 1 ? __('products') : __('product'),
                ])
            );
        }
    }

    private function deleteCategory(int $productCategoryId, int $eventId): void
    {
        $this->productCategoryRepository->deleteWhere(
            [
                ProductCategoryDomainObjectAbstract::ID => $productCategoryId,
                ProductCategoryDomainObjectAbstract::EVENT_ID => $eventId,
            ]
        );

        $this->logger->info(__('Product category :productCategoryId has been deleted.', [
            'product_category_id' => $productCategoryId,
            'event_id' => $eventId,
        ]));
    }

    /**
     * @throws CannotDeleteEntityException
     */
    private function validateCanDeleteProductCategory(int $eventId): void
    {
        $existingRelatedCategories = $this->productCategoryRepository->findWhere(
            [
                ProductCategoryDomainObjectAbstract::EVENT_ID => $eventId,
            ]
        );

        if ($existingRelatedCategories->count() === 1) {
            throw new CannotDeleteEntityException(
                __('You cannot delete the last product category. Please create another category before deleting this one.')
            );
        }
    }
}
