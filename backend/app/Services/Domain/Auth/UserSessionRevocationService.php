<?php

namespace HiEvents\Services\Domain\Auth;

use Carbon\Carbon;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;

class UserSessionRevocationService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function revokeAll(int $userId): void
    {
        $this->userRepository->updateWhere(
            attributes: ['tokens_valid_after' => now()->toDateTimeString()],
            where: ['id' => $userId],
        );
    }

    /**
     * @param  int[]  $userIds
     */
    public function revokeAllForUsers(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $this->userRepository->updateWhere(
            attributes: ['tokens_valid_after' => now()->toDateTimeString()],
            where: [['id', 'in', array_values(array_unique($userIds))]],
        );
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
