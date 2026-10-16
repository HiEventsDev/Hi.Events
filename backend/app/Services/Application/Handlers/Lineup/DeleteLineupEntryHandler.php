<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class DeleteLineupEntryHandler
{
    public function __construct(
        private readonly EventLineupEntryRepositoryInterface $lineupEntryRepository,
        private readonly EventScheduleItemRepositoryInterface $scheduleItemRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(int $eventId, int $entryId): void
    {
        $this->databaseManager->transaction(function () use ($eventId, $entryId) {
            $deleted = $this->lineupEntryRepository->deleteWhere([
                EventLineupEntryDomainObjectAbstract::ID => $entryId,
                EventLineupEntryDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

            if ($deleted === 0) {
                throw new ResourceNotFoundException(__('Lineup entry not found'));
            }

            $this->scheduleItemRepository->deleteUntitledWithoutPerformers([$eventId]);
        });
    }
}
