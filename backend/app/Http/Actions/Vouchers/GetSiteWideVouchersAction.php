<?php

namespace HiEvents\Http\Actions\Vouchers;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Resources\PromoCode\PromoCodeResource;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetSiteWideVouchersAction extends BaseAction
{
    public function __construct(
        private readonly PromoCodeRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();
        $params = QueryParamsDTO::fromArray($request->query());

        $vouchers = $this->repository->findByAccountId($accountId, $params);

        return $this->resourceResponse(
            resource: PromoCodeResource::class,
            data: $vouchers,
        );
    }
}
