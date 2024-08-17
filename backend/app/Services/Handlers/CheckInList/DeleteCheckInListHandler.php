<?php

namespace HiEvents\Services\Handlers\CheckInList;

use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class DeleteCheckInListHandler
{
    public function __construct(
        private readonly CheckInListRepositoryInterface $checkInListRepository,
    )
    {
    }

    public function handle(int $eventId, int $checkInListId): void
    {
        $checkInList = $this->checkInListRepository
            ->findFirstWhere([
                'event_id' => $eventId,
                'id' => $checkInListId,
            ]);

        if ($checkInList === null) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        $this->checkInListRepository->deleteWhere([
            'id' => $checkInListId,
            'event_id' => $eventId,
        ]);
    }
}
