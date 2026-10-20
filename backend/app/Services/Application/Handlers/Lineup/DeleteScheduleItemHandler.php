<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\Generated\EventScheduleItemDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;

class DeleteScheduleItemHandler
{
    public function __construct(
        private readonly EventScheduleItemRepositoryInterface $scheduleItemRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $scheduleItemId): void
    {
        $deleted = $this->scheduleItemRepository->deleteWhere([
            EventScheduleItemDomainObjectAbstract::ID => $scheduleItemId,
            EventScheduleItemDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($deleted === 0) {
            throw new ResourceNotFoundException(__('Schedule item not found'));
        }
    }
}
