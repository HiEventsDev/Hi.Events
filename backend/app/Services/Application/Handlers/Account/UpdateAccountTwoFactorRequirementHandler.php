<?php

namespace HiEvents\Services\Application\Handlers\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Exceptions\TwoFactorRequiredByAccountException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorAuthenticationService;

class UpdateAccountTwoFactorRequirementHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
    ) {}

    /**
     * @throws TwoFactorRequiredByAccountException
     */
    public function handle(int $accountId, int $actingUserId, bool $required): AccountDomainObject
    {
        if ($required && ! $this->twoFactorAuthenticationService->isEnabled($this->userRepository->findById($actingUserId))) {
            throw new TwoFactorRequiredByAccountException(
                __('Turn on two-factor authentication for your own profile before requiring it for everyone.')
            );
        }

        return $this->accountRepository->updateFromArray($accountId, [
            'require_two_factor_authentication' => $required,
        ]);
    }
}
