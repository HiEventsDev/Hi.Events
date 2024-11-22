<?php

namespace HiEvents\Http\Actions\ProductCategories;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\ProductCategory\ProductCategoryResource;
use HiEvents\Services\Application\Handlers\ProductCategory\GetProductCategoriesHandler;
use Illuminate\Http\JsonResponse;

class GetProductCategoriesAction extends BaseAction
{
    public function __construct(
        private readonly GetProductCategoriesHandler $getProductCategoriesHandler,
    )
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $categories = $this->getProductCategoriesHandler->handle($eventId);

        return $this->resourceResponse(
            resource: ProductCategoryResource::class,
            data: $categories,
        );
    }
}
