<?php

namespace HiEvents\Services\Domain\Auth;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\SsoProviderRegistry;

class PasswordLoginPolicy
{
    public function __construct(
        private readonly SsoProviderRegistry $providerRegistry,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
    ) {}

    public function isDisabled(): bool
    {
        return $this->providerRegistry->isPasswordLoginDisabled();
    }

    public function isAllowedFor(int $userId): bool
    {
        if (! $this->isDisabled()) {
            return true;
        }

        return $this->accountUserRepository->findFirstWhere([
            'user_id' => $userId,
            'role' => Role::SUPERADMIN->name,
        ]) !== null;
    }
}
