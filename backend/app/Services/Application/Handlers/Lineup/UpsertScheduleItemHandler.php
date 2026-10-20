<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventScheduleItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventScheduleItemPerformerDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSpaceDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemPerformerRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertScheduleItemDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpsertScheduleItemHandler
{
    public function __construct(
        private readonly EventScheduleItemRepositoryInterface $scheduleItemRepository,
        private readonly EventScheduleItemPerformerRepositoryInterface $performerRepository,
        private readonly EventLineupEntryRepositoryInterface $lineupEntryRepository,
        private readonly EventSpaceRepositoryInterface $spaceRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly HtmlPurifierService $purifier,
        private readonly EventSpamCheckDispatchService $spamCheckDispatchService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(UpsertScheduleItemDTO $dto): EventScheduleItemDomainObject
    {
        $this->assertSpaceBelongsToEvent($dto);
        $lineupEntryIds = $this->assertLineupEntriesBelongToEvent($dto);

        $timezone = $this->eventRepository->findById($dto->event_id)->getTimezone() ?? 'UTC';
        $description = $this->purifier->purify($dto->description);

        $attributes = [
            EventScheduleItemDomainObjectAbstract::TITLE => $dto->title === null || trim($dto->title) === ''
                ? null
                : trim($dto->title),
            EventScheduleItemDomainObjectAbstract::DESCRIPTION => $description === null || trim(strip_tags($description)) === ''
                ? null
                : $description,
            EventScheduleItemDomainObjectAbstract::STARTS_AT => DateHelper::convertToUTC($dto->starts_at, $timezone),
            EventScheduleItemDomainObjectAbstract::ENDS_AT => DateHelper::convertToUTC($dto->ends_at, $timezone),
            EventScheduleItemDomainObjectAbstract::EVENT_SPACE_ID => $dto->event_space_id,
        ];

        $itemId = $this->databaseManager->transaction(function () use ($dto, $attributes, $lineupEntryIds) {
            $itemId = $this->saveItem($dto, $attributes);
            $this->replacePerformers($itemId, $lineupEntryIds);

            return $itemId;
        });

        $this->spamCheckDispatchService->dispatchForEvent($dto->event_id);

        return $this->scheduleItemRepository
            ->loadRelation(new Relationship(EventScheduleItemPerformerDomainObject::class, name: 'performers'))
            ->findById($itemId);
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function saveItem(UpsertScheduleItemDTO $dto, array $attributes): int
    {
        if ($dto->schedule_item_id === null) {
            return $this->scheduleItemRepository->create([
                ...$attributes,
                EventScheduleItemDomainObjectAbstract::EVENT_ID => $dto->event_id,
            ])->getId();
        }

        $item = $this->scheduleItemRepository->findFirstWhere([
            EventScheduleItemDomainObjectAbstract::ID => $dto->schedule_item_id,
            EventScheduleItemDomainObjectAbstract::EVENT_ID => $dto->event_id,
        ]);

        if ($item === null) {
            throw new ResourceNotFoundException(__('Schedule item not found'));
        }

        $this->scheduleItemRepository->updateFromArray($item->getId(), $attributes);

        return $item->getId();
    }

    /**
     * @param  int[]  $lineupEntryIds
     */
    private function replacePerformers(int $itemId, array $lineupEntryIds): void
    {
        $this->performerRepository->deleteWhere([
            EventScheduleItemPerformerDomainObjectAbstract::EVENT_SCHEDULE_ITEM_ID => $itemId,
        ]);

        foreach ($lineupEntryIds as $position => $lineupEntryId) {
            $this->performerRepository->create([
                EventScheduleItemPerformerDomainObjectAbstract::EVENT_SCHEDULE_ITEM_ID => $itemId,
                EventScheduleItemPerformerDomainObjectAbstract::EVENT_LINEUP_ENTRY_ID => $lineupEntryId,
                EventScheduleItemPerformerDomainObjectAbstract::SORT_ORDER => $position,
            ]);
        }
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function assertSpaceBelongsToEvent(UpsertScheduleItemDTO $dto): void
    {
        if ($dto->event_space_id === null) {
            return;
        }

        $space = $this->spaceRepository->findFirstWhere([
            EventSpaceDomainObjectAbstract::ID => $dto->event_space_id,
            EventSpaceDomainObjectAbstract::EVENT_ID => $dto->event_id,
        ]);

        if ($space === null) {
            throw new ResourceNotFoundException(__('Stage or room not found'));
        }
    }

    /**
     * @return int[]
     *
     * @throws ResourceNotFoundException
     */
    private function assertLineupEntriesBelongToEvent(UpsertScheduleItemDTO $dto): array
    {
        $lineupEntryIds = array_values(array_unique(array_map('intval', $dto->lineup_entry_ids)));

        if ($lineupEntryIds === []) {
            return [];
        }

        $found = $this->lineupEntryRepository->findWhereIn(
            field: EventLineupEntryDomainObjectAbstract::ID,
            values: $lineupEntryIds,
            additionalWhere: [EventLineupEntryDomainObjectAbstract::EVENT_ID => $dto->event_id],
        );

        if ($found->count() !== count($lineupEntryIds)) {
            throw new ResourceNotFoundException(__('Lineup entry not found'));
        }

        return $lineupEntryIds;
    }
}
