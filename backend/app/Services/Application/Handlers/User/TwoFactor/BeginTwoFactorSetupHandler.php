<?php

namespace HiEvents\Services\Application\Handlers\User\TwoFactor;

use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\IdentityConfirmationService;
use HiEvents\Services\Domain\Auth\TwoFactor\DTO\TwoFactorSetupDTO;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;

class BeginTwoFactorSetupHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly IdentityConfirmationService $identityConfirmationService,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws IdentityConfirmationFailedException
     */
    public function handle(int $userId, ?string $password, ?string $reauthProof, ?string $tokenId): TwoFactorSetupDTO
    {
        $user = $this->userRepository->findById($userId);

        $this->identityConfirmationService->confirm($user, $password, $reauthProof, $tokenId);

        return $this->twoFactorAuthenticationService->beginSetup($user);
    }
}
