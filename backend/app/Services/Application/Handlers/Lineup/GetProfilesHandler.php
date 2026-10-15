<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\Generated\ProfileDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccess;
use Illuminate\Support\Collection;

class GetProfilesHandler
{
    public function __construct(
        private readonly ProfileRepositoryInterface $profileRepository,
    ) {}

    public function handle(int $organizerId, int $accountId, AccountUserAccess $access): Collection
    {
        $profiles = $this->profileRepository
            ->loadRelation(new Relationship(ImageDomainObject::class))
            ->loadRelation(new Relationship(EventLineupEntryDomainObject::class, nested: [
                new Relationship(EventDomainObject::class, name: 'event'),
            ], name: 'lineup_entries'))
            ->findWhere(
                where: [
                    ProfileDomainObjectAbstract::ORGANIZER_ID => $organizerId,
                    ProfileDomainObjectAbstract::ACCOUNT_ID => $accountId,
                ],
                orderAndDirections: [new OrderAndDirection(ProfileDomainObjectAbstract::NAME)],
            );

        return $profiles->map(fn (ProfileDomainObject $profile) => $profile->setLineupEntries(
            ($profile->getLineupEntries() ?? collect())
                ->filter(fn (EventLineupEntryDomainObject $entry) => $entry->getEvent() !== null
                    && $access->canAccessEvent($entry->getEventId(), $organizerId))
                ->values()
        ));
    }
}
