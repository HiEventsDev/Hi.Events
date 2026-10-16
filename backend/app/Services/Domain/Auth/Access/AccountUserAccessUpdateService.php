<?php

namespace HiEvents\Services\Domain\Auth\Access;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\AccountUserAccessScope;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Repository\Interfaces\AccountUserAccessGrantRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserPermissionRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Auth\Access\DTO\AccountUserAccessData;
use HiEvents\Services\Domain\Auth\Access\Exception\InvalidAccessGrantException;

readonly class AccountUserAccessUpdateService
{
    public function __construct(
        private AccountUserRepositoryInterface $accountUserRepository,
        private AccountUserAccessGrantRepositoryInterface $grantRepository,
        private AccountUserPermissionRepositoryInterface $permissionRepository,
        private OrganizerRepositoryInterface $organizerRepository,
        private EventRepositoryInterface $eventRepository,
        private AccountUserAccessService $accessService,
    ) {}

    /**
     * @throws InvalidAccessGrantException
     */
    public function update(
        AccountUserDomainObject $accountUser,
        Role $role,
        AccountUserAccessData $access,
    ): void {
        $scope = $role->canBeRestricted() ? $access->scope : AccountUserAccessScope::ACCOUNT;
        $organizerIds = [];
        $eventIds = [];

        if ($scope === AccountUserAccessScope::RESTRICTED) {
            $organizerIds = $this->uniqueIds($access->organizer_ids);
            $eventIds = $this->uniqueIds($access->event_ids);
            $this->assertGrantsBelongToAccount($accountUser->getAccountId(), $organizerIds, $eventIds);
        }

        $this->accountUserRepository->updateWhere(
            attributes: ['access_scope' => $scope->value],
            where: ['id' => $accountUser->getId()],
        );

        $this->grantRepository->deleteWhere(['account_user_id' => $accountUser->getId()]);

        foreach ($organizerIds as $organizerId) {
            $this->grantRepository->create([
                'account_user_id' => $accountUser->getId(),
                'organizer_id' => $organizerId,
            ]);
        }

        foreach ($eventIds as $eventId) {
            $this->grantRepository->create([
                'account_user_id' => $accountUser->getId(),
                'event_id' => $eventId,
            ]);
        }

        $this->permissionRepository->deleteWhere(['account_user_id' => $accountUser->getId()]);

        if ($role->canBeRestricted()) {
            $this->storePermissionOverrides($accountUser->getId(), $access->permissions);
        }

        $this->accessService->forget($accountUser->getId());
    }

    /**
     * @param  array<string, bool>  $permissions
     */
    private function storePermissionOverrides(int $accountUserId, array $permissions): void
    {
        foreach (Permission::adjustable() as $permission) {
            if (! array_key_exists($permission->value, $permissions)) {
                continue;
            }

            $this->permissionRepository->create([
                'account_user_id' => $accountUserId,
                'permission' => $permission->value,
                'granted' => (bool) $permissions[$permission->value],
            ]);
        }
    }

    /**
     * @param  int[]  $organizerIds
     * @param  int[]  $eventIds
     *
     * @throws InvalidAccessGrantException
     */
    private function assertGrantsBelongToAccount(int $accountId, array $organizerIds, array $eventIds): void
    {
        if ($organizerIds === [] && $eventIds === []) {
            throw new InvalidAccessGrantException(__('Choose at least one organizer or event.'));
        }

        $foundOrganizers = $organizerIds === []
            ? 0
            : $this->organizerRepository->findWhereIn('id', $organizerIds, ['account_id' => $accountId])->count();

        $foundEvents = $eventIds === []
            ? 0
            : $this->eventRepository->findWhereIn('id', $eventIds, ['account_id' => $accountId])->count();

        if ($foundOrganizers !== count($organizerIds) || $foundEvents !== count($eventIds)) {
            throw new InvalidAccessGrantException(__('One or more of the selected organizers or events could not be found.'));
        }
    }

    /**
     * @return int[]
     */
    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
