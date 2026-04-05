<?php

namespace HiEvents\Http\Actions\ProductBundles;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\ProductBundleDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\ProductBundleRepositoryInterface;
use HiEvents\Resources\ProductBundle\ProductBundleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateProductBundleAction extends BaseAction
{
    public function __construct(
        private readonly ProductBundleRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request, int $eventId, int $bundleId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'max_per_order' => 'nullable|integer|min:1',
            'quantity_available' => 'nullable|integer|min:0',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after:sale_start_date',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $bundle = $this->repository->updateByIdWhere(
            id: $bundleId,
            attributes: $validated,
            where: [
                [ProductBundleDomainObjectAbstract::EVENT_ID, '=', $eventId],
            ],
        );

        return $this->resourceResponse(
            resource: ProductBundleResource::class,
            data: $bundle,
        );
    }
}
