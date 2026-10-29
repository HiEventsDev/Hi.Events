<?php

namespace HiEvents\Services\Domain\Auth\Access;

use HiEvents\DomainObjects\AccountUserAccessGrantDomainObject;
use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\AccountUserPermissionDomainObject;
use HiEvents\DomainObjects\Enums\AccountUserAccessScope;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Repository\Interfaces\AccountUserAccessGrantRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserPermissionRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Support\Collection;

class AccountUserAccessService
{
    /** @var array<int, AccountUserAccess> */
    private array $resolved = [];

    /** @var array<string, AccountUserAccess|null> */
    private array $currentByIdentity = [];

    private bool $unrestricted = false;

    public function __construct(
        private readonly AuthUserService $authUserService,
        private readonly AccountUserAccessGrantRepositoryInterface $grantRepository,
        private readonly AccountUserPermissionRepositoryInterface $permissionRepository,
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function forCurrentUser(): ?AccountUserAccess
    {
        $identity = $this->authUserService->getAuthenticatedTokenKey() ?? '';

        if (! array_key_exists($identity, $this->currentByIdentity)) {
            $accountUser = $this->authUserService->getUser()?->getCurrentAccountUser();
            $this->currentByIdentity[$identity] = $accountUser === null ? null : $this->forAccountUser($accountUser);
        }

        return $this->currentByIdentity[$identity];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutMemberRestrictions(callable $callback): mixed
    {
        $previous = $this->unrestricted;
        $this->unrestricted = true;

        try {
            return $callback();
        } finally {
            $this->unrestricted = $previous;
        }
    }

    public function isWithheldFromCurrentUser(Permission $permission): bool
    {
        if ($this->unrestricted) {
            return false;
        }

        $access = $this->forCurrentUser();

        return $access !== null && ! $access->can($permission);
    }

    public function currentMemberCanAccessEvent(EventDomainObject $event, ?Permission $permission = null): bool
    {
        $access = $this->forActiveMemberOfAccount($event->getAccountId());

        return $access !== null
            && $access->canAccessEvent($event->getId(), $event->getOrganizerId())
            && ($permission === null || $access->can($permission));
    }

    public function currentMemberCanAccessOrganizer(OrganizerDomainObject $organizer): bool
    {
        return $this->forActiveMemberOfAccount($organizer->getAccountId())?->canAccessOrganizer($organizer->getId()) ?? false;
    }

    public function currentMemberCanReadOrganizer(OrganizerDomainObject $organizer): bool
    {
        return $this->forActiveMemberOfAccount($organizer->getAccountId())?->canReadOrganizer($organizer->getId()) ?? false;
    }

    public function forAccountUser(AccountUserDomainObject $accountUser): AccountUserAccess
    {
        $this->preload([$accountUser]);

        return $this->resolved[$accountUser->getId()];
    }

    /**
     * @param  iterable<AccountUserDomainObject|null>  $accountUsers
     */
    public function preload(iterable $accountUsers): void
    {
        $pending = collect($accountUsers)
            ->filter()
            ->reject(fn (AccountUserDomainObject $accountUser) => isset($this->resolved[$accountUser->getId()]))
            ->keyBy(fn (AccountUserDomainObject $accountUser) => $accountUser->getId());

        if ($pending->isNotEmpty()) {
            $this->resolved = $this->resolveMany($pending) + $this->resolved;
        }
    }

    public function forget(int $accountUserId): void
    {
        unset($this->resolved[$accountUserId]);
        $this->currentByIdentity = [];
    }

    private function forActiveMemberOfAccount(int $accountId): ?AccountUserAccess
    {
        if ($this->authUserService->getAuthenticatedAccountId() !== $accountId) {
            return null;
        }

        $accountUser = $this->authUserService->getUser()?->getCurrentAccountUser();

        if ($accountUser?->getStatus() !== UserStatus::ACTIVE->name) {
            return null;
        }

        return $this->forAccountUser($accountUser);
    }

    /**
     * @param  Collection<int, AccountUserDomainObject>  $accountUsers
     * @return array<int, AccountUserAccess>
     */
    private function resolveMany(Collection $accountUsers): array
    {
        $roles = $accountUsers->map(static fn (AccountUserDomainObject $accountUser) => Role::tryFrom((string) $accountUser->getRole()) ?? Role::VIEWER);
        $restrictableIds = $roles->filter(static fn (Role $role) => $role->canBeRestricted())->keys()->all();

        $overrides = $restrictableIds === []
            ? collect()
            : $this->permissionRepository
                ->findWhereIn('account_user_id', $restrictableIds)
                ->groupBy(static fn (AccountUserPermissionDomainObject $row) => $row->getAccountUserId());

        $restrictedIds = array_values(array_filter(
            $restrictableIds,
            static fn (int $id) => (AccountUserAccessScope::tryFrom((string) $accountUsers[$id]->getAccessScope()) ?? AccountUserAccessScope::RESTRICTED)
                === AccountUserAccessScope::RESTRICTED,
        ));

        $grants = $restrictedIds === []
            ? collect()
            : $this->grantRepository
                ->findLiveGrantsForAccountUsers($restrictedIds)
                ->groupBy(static fn (AccountUserAccessGrantDomainObject $grant) => $grant->getAccountUserId());

        $grantedEventIds = $grants->flatten(1)
            ->map(static fn (AccountUserAccessGrantDomainObject $grant) => $grant->getEventId())
            ->filter()
            ->unique()
            ->values()
            ->all();

        $events = $grantedEventIds === []
            ? collect()
            : $this->eventRepository
                ->findWhereIn('id', $grantedEventIds)
                ->keyBy(static fn (EventDomainObject $event) => $event->getId());

        return $accountUsers->map(function (AccountUserDomainObject $accountUser, int $id) use ($roles, $overrides, $restrictedIds, $grants, $events) {
            $permissions = $roles[$id]->permissionsWith(
                $overrides->get($id, collect())
                    ->mapWithKeys(static fn (AccountUserPermissionDomainObject $row) => [$row->getPermission() => $row->getGranted()])
                    ->all(),
            );

            if (! in_array($id, $restrictedIds, true)) {
                return new AccountUserAccess(AccountUserAccessScope::ACCOUNT, $permissions);
            }

            $memberGrants = $grants->get($id, collect());
            $memberEvents = $memberGrants
                ->map(static fn (AccountUserAccessGrantDomainObject $grant) => $events->get($grant->getEventId()))
                ->filter(static fn (?EventDomainObject $event) => $event?->getAccountId() === $accountUser->getAccountId());

            return new AccountUserAccess(
                scope: AccountUserAccessScope::RESTRICTED,
                permissions: $permissions,
                organizerIds: $memberGrants
                    ->map(static fn (AccountUserAccessGrantDomainObject $grant) => $grant->getOrganizerId())
                    ->filter()
                    ->values()
                    ->all(),
                eventIds: $memberEvents->map(static fn (EventDomainObject $event) => $event->getId())->values()->all(),
                parentOrganizerIds: $memberEvents->map(static fn (EventDomainObject $event) => $event->getOrganizerId())->unique()->values()->all(),
            );
        })->all();
    }
}
