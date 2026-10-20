<?php

namespace HiEvents\Services\Domain\Auth;

use Carbon\Carbon;
use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\Repository\Interfaces\PersonalAccessTokenRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;

class UserSessionRevocationService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PersonalAccessTokenRepositoryInterface $apiKeyRepository,
    ) {}

    public function revokeAll(int $userId, ?int $apiKeyAccountId = null): void
    {
        $this->revokeAllForUsers([$userId], $apiKeyAccountId);
    }

    /**
     * @param  int[]  $userIds
     */
    public function revokeAllForUsers(array $userIds, ?int $apiKeyAccountId = null): void
    {
        if ($userIds === []) {
            return;
        }

        $userIds = array_values(array_unique($userIds));

        $this->userRepository->updateWhere(
            attributes: ['tokens_valid_after' => now()->toDateTimeString()],
            where: [['id', 'in', $userIds]],
        );

        $this->apiKeyRepository->deleteWhere(array_filter([
            [PersonalAccessTokenDomainObject::TOKENABLE_ID, 'in', $userIds],
            $apiKeyAccountId === null ? null : [PersonalAccessTokenDomainObject::ACCOUNT_ID, '=', $apiKeyAccountId],
        ]));
    }

    public function isRevoked(?string $tokensValidAfter, int $issuedAt): bool
    {
        return $tokensValidAfter !== null && $issuedAt < Carbon::parse($tokensValidAfter)->getTimestamp();
    }

    public function isUserRevoked(int $userId, int $issuedAt): bool
    {
        $user = $this->userRepository->findFirstWhere(['id' => $userId]);

        return $user === null || $this->isRevoked($user->getTokensValidAfter(), $issuedAt);
    }
}
