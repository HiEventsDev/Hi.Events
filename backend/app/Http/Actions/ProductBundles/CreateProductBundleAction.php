<?php

namespace HiEvents\Http\Actions\ProductBundles;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\ProductBundleDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Repository\Interfaces\ProductBundleRepositoryInterface;
use HiEvents\Resources\ProductBundle\ProductBundleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CreateProductBundleAction extends BaseAction
{
    public function __construct(
        private readonly ProductBundleRepositoryInterface $repository,
    )
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'max_per_order' => 'nullable|integer|min:1',
            'quantity_available' => 'nullable|integer|min:0',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after:sale_start_date',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $bundle = $this->repository->create([
            ProductBundleDomainObjectAbstract::EVENT_ID => $eventId,
            ProductBundleDomainObjectAbstract::NAME => $validated['name'],
            ProductBundleDomainObjectAbstract::DESCRIPTION => $validated['description'] ?? null,
            ProductBundleDomainObjectAbstract::PRICE => $validated['price'],
            ProductBundleDomainObjectAbstract::CURRENCY => $validated['currency'] ?? 'USD',
            ProductBundleDomainObjectAbstract::MAX_PER_ORDER => $validated['max_per_order'] ?? null,
            ProductBundleDomainObjectAbstract::QUANTITY_AVAILABLE => $validated['quantity_available'] ?? null,
            ProductBundleDomainObjectAbstract::SALE_START_DATE => $validated['sale_start_date'] ?? null,
            ProductBundleDomainObjectAbstract::SALE_END_DATE => $validated['sale_end_date'] ?? null,
            ProductBundleDomainObjectAbstract::IS_ACTIVE => $validated['is_active'] ?? true,
            ProductBundleDomainObjectAbstract::SORT_ORDER => $validated['sort_order'] ?? 0,
        ]);

        return $this->resourceResponse(
            resource: ProductBundleResource::class,
            data: $bundle,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
