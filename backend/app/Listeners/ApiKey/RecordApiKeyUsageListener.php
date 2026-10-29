<?php

declare(strict_types=1);

namespace HiEvents\Listeners\ApiKey;

use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\Repository\Interfaces\PersonalAccessTokenRepositoryInterface;
use Laravel\Sanctum\Events\TokenAuthenticated;

class RecordApiKeyUsageListener
{
    private const RECORD_INTERVAL_MINUTES = 5;

    public function __construct(
        private readonly PersonalAccessTokenRepositoryInterface $apiKeyRepository,
    ) {}

    public function handle(TokenAuthenticated $event): void
    {
        $lastUsedAt = $event->token->last_used_at;

        if ($lastUsedAt !== null && $lastUsedAt->gt(now()->subMinutes(self::RECORD_INTERVAL_MINUTES))) {
            return;
        }

        $this->apiKeyRepository->updateWhere(
            attributes: [PersonalAccessTokenDomainObject::LAST_USED_AT => now()->toDateTimeString()],
            where: [PersonalAccessTokenDomainObject::ID => $event->token->getKey()],
        );
    }
}
