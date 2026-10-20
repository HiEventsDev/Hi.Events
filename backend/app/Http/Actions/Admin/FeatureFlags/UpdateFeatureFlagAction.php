<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\FeatureFlags;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Resources\FeatureFlag\FeatureFlagSummaryResource;
use HiEvents\Services\Application\Handlers\Admin\FeatureFlags\UpdateFeatureFlagHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateFeatureFlagAction extends BaseFeatureFlagAction
{
    public function __construct(
        private readonly UpdateFeatureFlagHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(Request $request, string $key): JsonResponse
    {
        $flag = $this->authorizeAndResolveFlag($key);

        $validated = $request->validate([
            'enabled_by_default' => 'required|boolean',
        ]);

        return $this->resourceResponse(
            resource: FeatureFlagSummaryResource::class,
            data: $this->handler->handle($flag, (bool) $validated['enabled_by_default']),
        );
    }
}
