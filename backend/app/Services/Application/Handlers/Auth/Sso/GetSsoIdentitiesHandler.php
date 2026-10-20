<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso;

use HiEvents\Enterprise\Sso\Services\Domain\SsoConnectionLinkService;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\UserIdentityRepositoryInterface;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\SsoSignInMethodsResult;

class GetSsoIdentitiesHandler
{
    public function __construct(
        private readonly UserIdentityRepositoryInterface $userIdentityRepository,
        private readonly SsoConnectionLinkService $connectionLinkService,
    ) {}

    public function handle(int $userId): SsoSignInMethodsResult
    {
        return new SsoSignInMethodsResult(
            identities: $this->userIdentityRepository->findWhere(
                where: ['user_id' => $userId],
                orderAndDirections: [new OrderAndDirection('created_at', 'asc')],
            ),
            linkableConnections: $this->connectionLinkService->linkableConnections($userId),
        );
    }
}
