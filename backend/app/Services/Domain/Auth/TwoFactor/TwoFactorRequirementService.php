<?php

namespace HiEvents\Services\Domain\Auth\TwoFactor;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use Illuminate\Support\Collection;

class TwoFactorRequirementService
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
    ) {}

    public function isRequiredByAccount(int $accountId): bool
    {
        return (bool) $this->accountRepository
            ->findFirstWhere(['id' => $accountId])
            ?->getRequireTwoFactorAuthentication();
    }

    /**
     * @return Collection<int, string>
     */
    public function namesOfAccountsRequiringTwoFactor(int $userId): Collection
    {
        return $this->accountUserRepository
            ->loadRelation(new Relationship(domainObject: AccountDomainObject::class, name: 'account'))
            ->findWhere([
                'user_id' => $userId,
                'status' => UserStatus::ACTIVE->name,
            ])
            ->map(fn (AccountUserDomainObject $accountUser) => $accountUser->getAccount())
            ->filter(fn (?AccountDomainObject $account) => $account?->getRequireTwoFactorAuthentication() === true)
            ->map(fn (AccountDomainObject $account) => $account->getName())
            ->values();
    }
}
