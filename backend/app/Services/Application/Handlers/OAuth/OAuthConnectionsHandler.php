<?php

namespace HiEvents\Services\Application\Handlers\OAuth;

use HiEvents\DomainObjects\OauthClientDomainObject;
use HiEvents\DomainObjects\OauthConnectionDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OauthConnectionRepositoryInterface;
use HiEvents\Services\Domain\Auth\OAuth\OAuthTokenService;
use Illuminate\Support\Collection;

class OAuthConnectionsHandler
{
    public function __construct(
        private readonly OauthConnectionRepositoryInterface $connectionRepository,
        private readonly OAuthTokenService $tokenService,
    ) {}

    /**
     * @return Collection<int, OauthConnectionDomainObject>
     */
    public function list(int $userId, int $accountId): Collection
    {
        return $this->connectionRepository
            ->loadRelation(new Relationship(OauthClientDomainObject::class, name: 'oauth_client'))
            ->findWhere(
                where: ['user_id' => $userId, 'account_id' => $accountId],
                orderAndDirections: [new OrderAndDirection('created_at', OrderAndDirection::DIRECTION_DESC)],
            );
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function disconnect(int $connectionId, int $userId, int $accountId): void
    {
        $connection = $this->connectionRepository->findFirstWhere([
            'id' => $connectionId,
            'user_id' => $userId,
            'account_id' => $accountId,
        ]);

        if ($connection === null) {
            throw new ResourceNotFoundException(__('Connected app not found.'));
        }

        $this->tokenService->revokeConnection($connectionId);
    }

    public function revokeToken(string $token): void
    {
        $this->tokenService->revokeByToken($token);
    }
}
