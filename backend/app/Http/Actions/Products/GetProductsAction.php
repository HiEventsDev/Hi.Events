<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Products;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Product\ProductResource;
use HiEvents\Services\Application\Handlers\Product\GetProductsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetProductsAction extends BaseAction
{
    public function __construct(
        private readonly GetProductsHandler $getProductsHandler,
    )
    {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $products = $this->getProductsHandler->handle(
            eventId: $eventId,
            queryParamsDTO: $this->getPaginationQueryParams($request),
        );

        return $this->filterableResourceResponse(
            resource: ProductResource::class,
            data: $products,
            domainObject: ProductDomainObject::class
        );
    }
}
