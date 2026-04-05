<?php

namespace HiEvents\Http\Actions\ProductBundles;

use HiEvents\DomainObjects\Generated\ProductBundleDomainObjectAbstract;
use HiEvents\Repository\Interfaces\ProductBundleRepositoryInterface;
use HiEvents\Resources\ProductBundle\ProductBundleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class GetProductBundlesPublicAction
{
    public function __construct(
        private readonly ProductBundleRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(int $eventId): JsonResponse
    {
        $bundles = $this->repository->findWhere([
            [ProductBundleDomainObjectAbstract::EVENT_ID, '=', $eventId],
            [ProductBundleDomainObjectAbstract::IS_ACTIVE, '=', true],
        ]);

        return response()->json([
            'data' => ProductBundleResource::collection($bundles),
        ]);
    }
}
