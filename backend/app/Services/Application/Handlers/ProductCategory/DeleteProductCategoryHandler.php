<?php

namespace HiEvents\Services\Application\Handlers\ProductCategory;

use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Services\Domain\ProductCategory\DeleteProductCategoryService;
use Throwable;

class DeleteProductCategoryHandler
{
    public function __construct(
        private readonly DeleteProductCategoryService $deleteProductCategoryService,
    )
    {
    }

    /**
     * @throws Throwable
     * @throws CannotDeleteEntityException
     */
    public function handle(int $productCategoryId, int $eventId): void
    {
        $this->deleteProductCategoryService->deleteProductCategory($productCategoryId, $eventId);
    }
}
