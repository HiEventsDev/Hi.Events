<?php

namespace HiEvents\Http\Actions\ProductBundles;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\ProductBundleDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\ProductBundleRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeleteProductBundleAction extends BaseAction
{
    public function __construct(
        private readonly ProductBundleRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request, int $eventId, int $bundleId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->repository->deleteWhere([
            [ProductBundleDomainObjectAbstract::ID, '=', $bundleId],
            [ProductBundleDomainObjectAbstract::EVENT_ID, '=', $eventId],
        ]);

        return $this->deletedResponse();
    }
}
