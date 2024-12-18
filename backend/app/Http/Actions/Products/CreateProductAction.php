<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Products;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\InvalidTaxOrFeeIdException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Product\UpsertProductRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Product\ProductResource;
use HiEvents\Services\Application\Handlers\Product\CreateProductHandler;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateProductAction extends BaseAction
{
    private CreateProductHandler $createProductHandler;

    public function __construct(CreateProductHandler $handler)
    {
        $this->createProductHandler = $handler;
    }

    /**
     * @throws Throwable
     */
    public function __invoke(int $eventId, UpsertProductRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $request->merge([
            'event_id' => $eventId,
            'account_id' => $this->getAuthenticatedAccountId(),
        ]);

        try {
            $product = $this->createProductHandler->handle(UpsertProductDTO::fromArray($request->all()));
        } catch (InvalidTaxOrFeeIdException $e) {
            throw ValidationException::withMessages([
                'tax_and_fee_ids' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: ProductResource::class,
            data: $product,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
