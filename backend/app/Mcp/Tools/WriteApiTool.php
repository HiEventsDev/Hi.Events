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

    public const RESEND_COOLDOWN_SECONDS = 600;

    public const EMAILS_PER_ADDRESS_PER_COOLDOWN = 5;

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

    protected function resendCooldownResponse(string $recipientKey): ?Response
    {
        if (RateLimiter::tooManyAttempts('mcp-resend:'.$recipientKey, 1)) {
            return Response::error(__('This was sent less than :minutes minutes ago. Wait before sending it again.', [
                'minutes' => intdiv(self::RESEND_COOLDOWN_SECONDS, 60),
            ]));
        }

        return null;
    }

    protected function startResendCooldown(string $recipientKey): void
    {
        RateLimiter::hit('mcp-resend:'.$recipientKey, self::RESEND_COOLDOWN_SECONDS);
    }

    protected function emailBudgetResponse(string $email): ?Response
    {
        if (RateLimiter::tooManyAttempts($this->emailBudgetKey($email), self::EMAILS_PER_ADDRESS_PER_COOLDOWN)) {
            return Response::error(__('That email address has already been sent :count emails in the last :minutes minutes. Wait before sending more.', [
                'count' => self::EMAILS_PER_ADDRESS_PER_COOLDOWN,
                'minutes' => intdiv(self::RESEND_COOLDOWN_SECONDS, 60),
            ]));
        }

        return null;
    }

    protected function spendEmailBudget(string $email): void
    {
        RateLimiter::hit($this->emailBudgetKey($email), self::RESEND_COOLDOWN_SECONDS);
    }

    private function emailBudgetKey(string $email): string
    {
        return 'mcp-mail:'.hash('sha256', mb_strtolower(trim($email)));
    }
}
