<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\FeatureFlags;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Resources\FeatureFlag\FeatureFlagOverrideResource;
use HiEvents\Services\Application\Handlers\Admin\FeatureFlags\GetFeatureFlagOverridesHandler;
use Illuminate\Http\JsonResponse;

class GetFeatureFlagOverridesAction extends BaseFeatureFlagAction
{
    public function __construct(
        private readonly GetFeatureFlagOverridesHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(string $key): JsonResponse
    {
        return $this->resourceResponse(
            resource: FeatureFlagOverrideResource::class,
            data: $this->handler->handle($this->authorizeAndResolveFlag($key)),
        );
    }
}
