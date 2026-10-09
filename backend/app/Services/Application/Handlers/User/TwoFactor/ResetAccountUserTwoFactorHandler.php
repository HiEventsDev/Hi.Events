<?php

namespace HiEvents\Services\Application\Handlers\User\TwoFactor;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorResetService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResetAccountUserTwoFactorHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly TwoFactorResetService $twoFactorResetService,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws UnauthorizedException
     */
    public function handle(int $accountId, int $targetUserId, int $actingUserId): void
    {
        if ($targetUserId === $actingUserId) {
            throw new UnauthorizedException(__('Use your profile security settings to manage your own two-factor authentication.'));
        }

        $target = $this->accountUserRepository->findFirstWhere([
            'account_id' => $accountId,
            'user_id' => $targetUserId,
        ]);

        if ($target === null) {
            throw new NotFoundHttpException(__('User not found'));
        }

        $actor = $this->accountUserRepository->findFirstWhere([
            'account_id' => $accountId,
            'user_id' => $actingUserId,
        ]);

        if ($target->getRole() === Role::SUPERADMIN->name
            || ($target->getIsAccountOwner() && ! $actor?->getIsAccountOwner())) {
            throw new UnauthorizedException(__('You do not have permission to reset two-factor authentication for this user.'));
        }

        $otherMemberships = $this->accountUserRepository->countWhere([
            'user_id' => $targetUserId,
            ['account_id', '!=', $accountId],
        ]);

        if ($otherMemberships > 0) {
            throw new UnauthorizedException(__('This user also belongs to other accounts, so only support can reset their two-factor authentication.'));
        }

        $this->twoFactorResetService->reset($this->userRepository->findById($targetUserId), $actingUserId);
    }
}
