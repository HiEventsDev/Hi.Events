<?php

namespace HiEvents\Http\Actions\Vouchers;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteSiteWideVoucherAction extends BaseAction
{
    public function __construct(
        private readonly PromoCodeRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(int $voucherId): JsonResponse
    {
        $this->repository->deleteById($voucherId);

        return $this->deletedResponse();
    }
}
