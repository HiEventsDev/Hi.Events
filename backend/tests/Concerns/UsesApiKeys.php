<?php

namespace Tests\Concerns;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyService;
use Illuminate\Support\Facades\Mail;

trait UsesApiKeys
{
    protected function createApiKey(
        User $user,
        int $accountId,
        ApiKeyAccess $access = ApiKeyAccess::READ_WRITE,
        ?int $expiresInDays = 90,
        string $name = 'Test key',
    ): string {
        Mail::fake();

        return app(ApiKeyService::class)->create(
            user: app(UserRepositoryInterface::class)->findById($user->id),
            accountId: $accountId,
            name: $name,
            access: $access,
            expiresInDays: $expiresInDays,
        )->plainTextKey;
    }

    protected function apiKeyHeaders(string $apiKey): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => 'Bearer '.$apiKey];
    }
}
