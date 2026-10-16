<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProfileDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class DeleteProfileHandler
{
    public function __construct(
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly EventLineupEntryRepositoryInterface $lineupEntryRepository,
        private readonly EventScheduleItemRepositoryInterface $scheduleItemRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(int $profileId, int $organizerId, int $accountId): void
    {
        $profile = $this->profileRepository->findFirstWhere([
            ProfileDomainObjectAbstract::ID => $profileId,
            ProfileDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            ProfileDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);

        if ($profile === null) {
            throw new ResourceNotFoundException(__('Profile not found'));
        }

        $this->databaseManager->transaction(function () use ($profileId) {
            $eventIds = $this->lineupEntryRepository
                ->findWhere([EventLineupEntryDomainObjectAbstract::PROFILE_ID => $profileId])
                ->map(fn (EventLineupEntryDomainObject $entry) => $entry->getEventId())
                ->unique()
                ->values()
                ->all();

            $this->lineupEntryRepository->deleteWhere([
                EventLineupEntryDomainObjectAbstract::PROFILE_ID => $profileId,
            ]);
            $this->profileRepository->deleteById($profileId);

            if ($eventIds !== []) {
                $this->scheduleItemRepository->deleteUntitledWithoutPerformers($eventIds);
            }
        });
    }
}
