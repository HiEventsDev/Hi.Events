<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Http\Actions\ProductCategories\GetProductCategoriesAction;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessService;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

abstract class WriteApiTool extends ApiTool
{
    public const MAX_WRITES_PER_MINUTE = 60;

    public function shouldRegister(AuthUserService $authUserService, AccountUserAccessService $accessService): bool
    {
        return $authUserService->apiKeyCanWrite()
            && ($accessService->forCurrentUser()?->can(Permission::EDIT) ?? false);
    }

    protected function writeLimitResponse(Request $request, int $writes = 1): ?Response
    {
        $key = 'mcp-write:'.$request->user()?->getAuthIdentifier().':'.$request->user()?->currentAccessToken()?->getKey();

        if (RateLimiter::attempts($key) + $writes > self::MAX_WRITES_PER_MINUTE) {
            return Response::error(__('Too many changes in a short time. Wait a minute and try again.'));
        }

        RateLimiter::increment($key, amount: $writes);

        return null;
    }

    protected function resolveCategoryId(int $eventId, ?int $categoryId): int|Response|null
    {
        if ($categoryId !== null) {
            return $categoryId;
        }

        $categoriesResponse = $this->api->get(GetProductCategoriesAction::class, ['event_id' => $eventId]);

        if (! $categoriesResponse->isSuccessful()) {
            return $this->errorResponse($categoriesResponse);
        }

        return $categoriesResponse->data()[0]['id'] ?? null;
    }
}
