<?php

namespace HiEvents\Services\Domain\Auth\OAuth;

use HiEvents\Repository\Interfaces\OauthClientRepositoryInterface;
use HiEvents\Repository\Interfaces\OauthConnectionRepositoryInterface;
use HiEvents\Repository\Interfaces\OauthRefreshTokenRepositoryInterface;

class OAuthPruneService
{
    public const IDLE_CLIENT_DAYS = 180;

    public function __construct(
        private readonly OauthRefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly OauthConnectionRepositoryInterface $connectionRepository,
        private readonly OauthClientRepositoryInterface $clientRepository,
    ) {}

    public function prune(): void
    {
        $this->refreshTokenRepository->deleteWhere([['expires_at', '<', now()]]);
        $this->connectionRepository->deleteWithoutRefreshTokens();
        $this->clientRepository->deleteIdleWithoutConnections(now()->subDays(self::IDLE_CLIENT_DAYS));
    }
}
