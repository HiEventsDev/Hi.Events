<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorResetService;

class ResetUserTwoFactorHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TwoFactorResetService $twoFactorResetService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(int $userId, ?int $resetByUserId): void
    {
        $this->twoFactorResetService->reset($this->userRepository->findById($userId), $resetByUserId);
    }
}
