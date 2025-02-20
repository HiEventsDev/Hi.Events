<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Products;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CannotChangeProductTypeException;
use HiEvents\Exceptions\InvalidTaxOrFeeIdException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Product\UpsertProductRequest;
use HiEvents\Resources\Product\ProductResource;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Application\Handlers\Product\EditProductHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditProductAction extends BaseAction
{
    public function __construct(
        private readonly EditProductHandler $editProductHandler,
    )
    {
    }

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function __invoke(UpsertProductRequest $request, int $eventId, int $productId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $request->merge([
            'event_id' => $eventId,
            'account_id' => $this->getAuthenticatedAccountId(),
            'product_id' => $productId,
        ]);

        try {
            $product = $this->editProductHandler->handle(UpsertProductDTO::fromArray($request->all()));
        } catch (InvalidTaxOrFeeIdException $e) {
            throw ValidationException::withMessages([
                'tax_and_fee_ids' => $e->getMessage(),
            ]);
        } catch (CannotChangeProductTypeException $e) {
            throw ValidationException::withMessages([
                'type' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(ProductResource::class, $product);
    }
}
