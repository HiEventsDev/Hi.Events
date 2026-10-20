<?php

namespace HiEvents\Services\Domain\Auth\Access;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\Enums\TeamAccessVia;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Access\DTO\TeamMemberAccessDTO;
use Illuminate\Support\Collection;

class TeamAccessQueryService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountUserAccessService $accessService,
    ) {}

    /**
     * @return Collection<int, TeamMemberAccessDTO>
     */
    public function membersForEvent(EventDomainObject $event): Collection
    {
        return $this->members($event->getAccountId(), function (AccountUserAccess $access) use ($event) {
            if (! $access->isRestricted()) {
                return TeamAccessVia::ACCOUNT;
            }

            if (in_array($event->getOrganizerId(), $access->organizerIds, true)) {
                return TeamAccessVia::ORGANIZER;
            }

            return in_array($event->getId(), $access->eventIds, true) ? TeamAccessVia::EVENT : null;
        });
    }

    /**
     * @return Collection<int, TeamMemberAccessDTO>
     */
    public function membersForOrganizer(int $accountId, int $organizerId): Collection
    {
        return $this->members($accountId, function (AccountUserAccess $access) use ($organizerId) {
            if (! $access->isRestricted()) {
                return TeamAccessVia::ACCOUNT;
            }

            return in_array($organizerId, $access->organizerIds, true) ? TeamAccessVia::ORGANIZER : null;
        });
    }

    /**
     * @param  callable(AccountUserAccess): ?TeamAccessVia  $accessVia
     * @return Collection<int, TeamMemberAccessDTO>
     */
    private function members(int $accountId, callable $accessVia): Collection
    {
        $users = $this->userRepository
            ->loadRelation(new Relationship(domainObject: AccountUserDomainObject::class, name: 'currentAccountUser'))
            ->findUsersByAccountId($accountId);

        $this->accessService->preload($users->map(fn (UserDomainObject $user) => $user->getCurrentAccountUser()));

        return $users
            ->map(function (UserDomainObject $user) use ($accessVia) {
                $accountUser = $user->getCurrentAccountUser();

                if ($accountUser === null) {
                    return null;
                }

                $via = $accessVia($this->accessService->forAccountUser($accountUser));

                return $via === null ? null : new TeamMemberAccessDTO(
                    user_id: $user->getId(),
                    first_name: $user->getFirstName(),
                    last_name: $user->getLastName(),
                    email: $user->getEmail(),
                    role: $accountUser->getRole(),
                    status: $accountUser->getStatus(),
                    access_via: $via,
                );
            })
            ->filter()
            ->values();
    }
}
