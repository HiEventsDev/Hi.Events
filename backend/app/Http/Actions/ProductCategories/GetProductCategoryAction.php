<?php

namespace HiEvents\Http\Actions\ProductCategories;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\ProductCategory\ProductCategoryResource;
use HiEvents\Services\Application\Handlers\ProductCategory\GetProductCategoryHandler;
use Illuminate\Http\JsonResponse;

class GetProductCategoryAction extends BaseAction
{
    public function __construct(
        private readonly GetProductCategoryHandler $getProductCategoryHandler,
    )
    {
    }

    public function __invoke(int $eventId, int $productCategoryId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $category = $this->getProductCategoryHandler->handle($eventId, $productCategoryId);

        return $this->resourceResponse(
            resource: ProductCategoryResource::class,
            data: $category,
        );
    }
}
