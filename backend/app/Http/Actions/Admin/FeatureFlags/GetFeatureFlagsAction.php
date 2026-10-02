<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\FeatureFlags;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\FeatureFlag\FeatureFlagSummaryResource;
use HiEvents\Services\Application\Handlers\Admin\FeatureFlags\GetFeatureFlagsHandler;
use Illuminate\Http\JsonResponse;

class GetFeatureFlagsAction extends BaseAction
{
    public function __construct(
        private readonly GetFeatureFlagsHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        return $this->resourceResponse(
            resource: FeatureFlagSummaryResource::class,
            data: $this->handler->handle(),
        );
    }
}
