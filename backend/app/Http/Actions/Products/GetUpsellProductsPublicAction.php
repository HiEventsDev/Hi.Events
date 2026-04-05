<?php

namespace HiEvents\Http\Actions\Products;

use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Resources\Product\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetUpsellProductsPublicAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $selectedProductIds = $request->query('product_ids', []);

        if (is_string($selectedProductIds)) {
            $selectedProductIds = explode(',', $selectedProductIds);
        }

        $selectedProductIds = array_map('intval', array_filter($selectedProductIds));

        $upsellProducts = $this->productRepository->findWhere([
            [ProductDomainObjectAbstract::EVENT_ID, '=', $eventId],
            [ProductDomainObjectAbstract::IS_UPSELL, '=', true],
        ]);

        // Filter upsell products to only those targeting the selected products
        $filteredUpsells = $upsellProducts->filter(function ($product) use ($selectedProductIds) {
            $targetIds = $product->getUpsellForProductIds();
            if ($targetIds === null || $targetIds === []) {
                return true; // No target restriction = upsells for all products
            }

            if (is_string($targetIds)) {
                $targetIds = json_decode($targetIds, true) ?? [];
            }

            return !empty(array_intersect($targetIds, $selectedProductIds));
        });

        return response()->json([
            'data' => ProductResource::collection($filteredUpsells->values()),
        ]);
    }
}
