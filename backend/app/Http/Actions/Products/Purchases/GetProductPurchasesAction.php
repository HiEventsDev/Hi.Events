<?php

namespace HiEvents\Http\Actions\Products\Purchases;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Product\ProductPurchaseResource;
use HiEvents\Services\Application\Handlers\Product\Purchases\DTO\GetProductPurchasesDTO;
use HiEvents\Services\Application\Handlers\Product\Purchases\GetProductPurchasesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GetProductPurchasesAction extends BaseAction
{
    public function __construct(
        private readonly GetProductPurchasesHandler $handler,
    ) {}

    public function __invoke(Request $request, int $eventId, int $productId): JsonResponse|Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $purchases = $this->handler->handle(new GetProductPurchasesDTO(
                eventId: $eventId,
                productId: $productId,
                queryParams: $this->getPaginationQueryParams($request),
            ));
        } catch (ResourceNotFoundException) {
            return $this->notFoundResponse();
        }

        return $this->resourceResponse(ProductPurchaseResource::class, $purchases);
    }
}
