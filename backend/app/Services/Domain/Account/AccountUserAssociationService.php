<?php

namespace HiEvents\Services\Domain\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;

readonly class AccountUserAssociationService
{
    public function __construct(
        private AccountUserRepositoryInterface $accountUserRepository,
    )
    {
    }

    public function associate(
        UserDomainObject    $user,
        AccountDomainObject $account,
        Role                $role,
        ?UserStatus         $status = null,
        ?int                $invitedByUserId = null,
        bool                $isAccountOwner = false,
    ): AccountUserDomainObject
    {
        $data = [
            'user_id' => $user->getId(),
            'account_id' => $account->getId(),
            'role' => $role->name,
            'is_account_owner' => $isAccountOwner,
        ];

        if ($status !== null) {
            $data['status'] = $status->name;
        }

        if ($invitedByUserId !== null) {
            $data['invited_by_user_id'] = $invitedByUserId;
        }

        return $this->accountUserRepository->create($data);
    }
}
