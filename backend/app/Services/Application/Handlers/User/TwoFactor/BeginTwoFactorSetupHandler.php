<?php

namespace HiEvents\Services\Application\Handlers\User\TwoFactor;

use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\TwoFactor\DTO\TwoFactorSetupDTO;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;
use Illuminate\Contracts\Hashing\Hasher;

class BeginTwoFactorSetupHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly Hasher $hasher,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws PasswordInvalidException
     */
    public function handle(int $userId, string $password): TwoFactorSetupDTO
    {
        $user = $this->userRepository->findById($userId);

        if (! $this->hasher->check($password, $user->getPassword())) {
            throw new PasswordInvalidException(__('The password is incorrect.'));
        }

        return $this->twoFactorAuthenticationService->beginSetup($user);
    }
}
