<?php

namespace HiEvents\Http\Actions\ProductBundles;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\ProductBundleRepositoryInterface;
use HiEvents\Resources\ProductBundle\ProductBundleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetProductBundlesAction extends BaseAction
{
    public function __construct(
        private readonly ProductBundleRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $params = QueryParamsDTO::fromArray($request->query());
        $bundles = $this->repository->findByEventId($eventId, $params);

        return $this->resourceResponse(
            resource: ProductBundleResource::class,
            data: $bundles,
        );
    }
}
