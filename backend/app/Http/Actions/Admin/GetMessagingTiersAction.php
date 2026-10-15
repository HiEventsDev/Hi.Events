<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Resources\Admin\AccountMessagingTierResource;
use HiEvents\Repository\Interfaces\AccountMessagingTierRepositoryInterface;
use Illuminate\Http\JsonResponse;

class GetMessagingTiersAction extends BaseAction
{
    public function __construct(
        private readonly AccountMessagingTierRepositoryInterface $messagingTierRepository,
    ) {}

    public function __invoke(): JsonResponse
    {
        $this->requireSuperAdmin();

        $tiers = $this->messagingTierRepository->all();

        return $this->resourceResponse(
            resource: AccountMessagingTierResource::class,
            data: $tiers
        );
    }
}
