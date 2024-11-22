<?php

namespace HiEvents\Http\Actions\ProductCategories;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\ProductCategory\UpsertProductCategoryRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\ProductCategory\ProductCategoryResource;
use HiEvents\Services\Application\Handlers\ProductCategory\CreateProductCategoryHandler;
use HiEvents\Services\Application\Handlers\ProductCategory\DTO\UpsertProductCategoryDTO;
use Illuminate\Http\JsonResponse;

class CreateProductCategoryAction extends BaseAction
{
    public function __construct(
        private readonly CreateProductCategoryHandler $handler
    )
    {
    }

    public function __invoke(UpsertProductCategoryRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $productCategory = $this->handler->handle(new UpsertProductCategoryDTO(
            name: $request->validated('name'),
            description: $request->validated('description'),
            is_hidden: $request->validated('is_hidden'),
            event_id: $eventId,
            no_products_message: $request->validated('no_products_message'),
        ));

        return $this->resourceResponse(
            resource: ProductCategoryResource::class,
            data: $productCategory,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
