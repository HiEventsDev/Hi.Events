<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\FeatureFlags;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\FeatureFlag\AccountFeatureFlagResource;
use HiEvents\Services\Application\Handlers\Admin\FeatureFlags\GetAccountFeatureFlagsHandler;
use Illuminate\Http\JsonResponse;

class GetAccountFeatureFlagsAction extends BaseAction
{
    public function __construct(
        private readonly GetAccountFeatureFlagsHandler $handler,
    ) {}

    public function __invoke(int $accountId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        return $this->resourceResponse(
            resource: AccountFeatureFlagResource::class,
            data: $this->handler->handle($accountId)->values(),
        );
    }
}
