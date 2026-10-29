<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\DTO\AuthContext;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorResetService;

class ResetUserTwoFactorHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TwoFactorResetService $twoFactorResetService,
        private readonly SsoEnforcementService $ssoEnforcementService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $userId, ?int $resetByUserId): void
    {
        if ($this->ssoEnforcementService->requiresFreshSecondFactor($userId, AuthContext::password())) {
            throw new ResourceConflictException(__('This user owns an account that requires single sign-on. Two-factor authentication is their backup sign-in, so ask them to stop requiring single sign-on first.'));
        }

        $this->twoFactorResetService->reset($this->userRepository->findById($userId), $resetByUserId);
    }
}
