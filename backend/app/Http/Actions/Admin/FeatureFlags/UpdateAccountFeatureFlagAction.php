<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\FeatureFlags;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Resources\FeatureFlag\AccountFeatureFlagResource;
use HiEvents\Services\Application\Handlers\Admin\FeatureFlags\UpdateAccountFeatureFlagHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateAccountFeatureFlagAction extends BaseFeatureFlagAction
{
    public function __construct(
        private readonly UpdateAccountFeatureFlagHandler $handler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(Request $request, int $accountId, string $key): JsonResponse
    {
        $flag = $this->authorizeAndResolveFlag($key);

        $validated = $request->validate([
            'enabled' => 'present|nullable|boolean',
        ]);

        return $this->resourceResponse(
            resource: AccountFeatureFlagResource::class,
            data: $this->handler->handle(
                accountId: $accountId,
                flag: $flag,
                enabled: $validated['enabled'] === null ? null : (bool) $validated['enabled'],
            )->values(),
        );
    }
}
