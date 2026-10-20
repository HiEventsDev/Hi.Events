<?php

namespace HiEvents\Services\Domain\Auth\Access;

use HiEvents\DomainObjects\Enums\AccountUserAccessScope;
use HiEvents\DomainObjects\Enums\Permission;

readonly class AccountUserAccess
{
    /**
     * @param  Permission[]  $permissions
     * @param  int[]  $organizerIds
     * @param  int[]  $eventIds
     * @param  int[]  $parentOrganizerIds
     */
    public function __construct(
        public AccountUserAccessScope $scope,
        public array $permissions,
        public array $organizerIds = [],
        public array $eventIds = [],
        public array $parentOrganizerIds = [],
    ) {}

    public function isRestricted(): bool
    {
        return $this->scope === AccountUserAccessScope::RESTRICTED;
    }

    public function can(Permission $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function canAccessOrganizer(int $organizerId): bool
    {
        return ! $this->isRestricted() || in_array($organizerId, $this->organizerIds, true);
    }

    public function canReadOrganizer(int $organizerId): bool
    {
        return $this->canAccessOrganizer($organizerId) || in_array($organizerId, $this->parentOrganizerIds, true);
    }

    public function canAccessEvent(int $eventId, ?int $organizerId): bool
    {
        return ! $this->isRestricted()
            || in_array($organizerId, $this->organizerIds, true)
            || in_array($eventId, $this->eventIds, true);
    }

    /**
     * @return int[]
     */
    public function readableOrganizerIds(): array
    {
        return array_values(array_unique([...$this->organizerIds, ...$this->parentOrganizerIds]));
    }
}
