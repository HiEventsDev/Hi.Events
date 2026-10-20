<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class SortLineupEntriesHandler
{
    public function __construct(
        private readonly EventLineupEntryRepositoryInterface $lineupEntryRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @param  int[]  $sortedEntryIds
     *
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(int $eventId, array $sortedEntryIds): void
    {
        $eventEntryIds = $this->lineupEntryRepository
            ->findWhere(
                where: [EventLineupEntryDomainObjectAbstract::EVENT_ID => $eventId],
                orderAndDirections: [
                    new OrderAndDirection(EventLineupEntryDomainObjectAbstract::SORT_ORDER),
                    new OrderAndDirection(EventLineupEntryDomainObjectAbstract::ID),
                ],
            )
            ->map(fn ($entry) => $entry->getId())
            ->all();

        if (array_diff($sortedEntryIds, $eventEntryIds) !== []) {
            throw new ResourceNotFoundException(__('Lineup entry not found'));
        }

        $ordered = array_values(array_unique([...$sortedEntryIds, ...$eventEntryIds]));

        $this->databaseManager->transaction(function () use ($eventId, $ordered) {
            foreach ($ordered as $position => $entryId) {
                $this->lineupEntryRepository->updateWhere(
                    attributes: [EventLineupEntryDomainObjectAbstract::SORT_ORDER => $position],
                    where: [
                        EventLineupEntryDomainObjectAbstract::ID => $entryId,
                        EventLineupEntryDomainObjectAbstract::EVENT_ID => $eventId,
                    ],
                );
            }
        });
    }
}
