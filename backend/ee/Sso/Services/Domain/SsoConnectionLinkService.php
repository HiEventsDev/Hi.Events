<?php

namespace HiEvents\Enterprise\Sso\Services\Domain;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Status\AccountSsoConnectionStatus;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Enterprise\Sso\Services\Domain\DTO\LinkableSsoConnection;
use HiEvents\Exceptions\SsoException;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use Illuminate\Support\Collection;

class SsoConnectionLinkService
{
    public function __construct(
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly AccountSsoConnectionRepositoryInterface $connectionRepository,
    ) {}

    /**
     * @throws SsoException
     */
    public function assertCanLink(SsoProviderConfig $provider, int $userId): void
    {
        if (! $this->canLink((int) $provider->accountId, $userId)) {
            throw new SsoException(SsoErrorCode::PROVIDER_NOT_CONFIGURED, __('This sign-in option is not available.'));
        }
    }

    public function canLink(int $accountId, int $userId): bool
    {
        return ! $this->isSuperAdmin($userId) && $this->accountUserRepository->findFirstWhere([
            'user_id' => $userId,
            'account_id' => $accountId,
            'status' => UserStatus::ACTIVE->name,
        ]) !== null;
    }

    public function isSuperAdmin(int $userId): bool
    {
        return $this->accountUserRepository->findFirstWhere([
            'user_id' => $userId,
            'role' => Role::SUPERADMIN->name,
        ]) !== null;
    }

    /**
     * @return Collection<int, LinkableSsoConnection>
     */
    public function linkableConnections(int $userId): Collection
    {
        if ($this->isSuperAdmin($userId)) {
            return collect();
        }

        $accountNames = $this->accountUserRepository
            ->loadRelation(new Relationship(AccountDomainObject::class, name: 'account'))
            ->findWhere(['user_id' => $userId, 'status' => UserStatus::ACTIVE->name])
            ->mapWithKeys(fn (AccountUserDomainObject $accountUser) => [$accountUser->getAccountId() => (string) $accountUser->getAccount()?->getName()]);

        if ($accountNames->isEmpty()) {
            return collect();
        }

        return $this->connectionRepository
            ->findWhere(
                where: [
                    ['account_id', 'in', $accountNames->keys()->all()],
                    'status' => AccountSsoConnectionStatus::ACTIVE->value,
                ],
                orderAndDirections: [new OrderAndDirection('id', 'asc')],
            )
            ->map(fn (AccountSsoConnectionDomainObject $connection) => new LinkableSsoConnection(
                key: $connection->getProviderKey(),
                name: $connection->getConnectionType()->label(),
                type: $connection->getConnectionType()->providerType(),
                accountName: $accountNames->get($connection->getAccountId(), ''),
            ))
            ->values();
    }
}
