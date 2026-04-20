<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\Repository\DTO\CheckInListStatsDTO;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCheckInListStatsPublicHandler
{
    private const RECENT_CHECK_INS_LIMIT = 20;

    public function __construct(
        private readonly CheckInListRepositoryInterface $checkInListRepository,
    )
    {
    }

    public function handle(string $shortId): CheckInListStatsDTO
    {
        $checkInList = $this->checkInListRepository->findFirstWhere(['short_id' => $shortId]);

        if (!$checkInList) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        $totals = $this->checkInListRepository->getCheckedInAttendeeCountById($checkInList->getId());
        $perProduct = $this->checkInListRepository->getPerProductCheckInStatsById($checkInList->getId());
        $recent = $this->checkInListRepository->getRecentCheckInsById($checkInList->getId(), self::RECENT_CHECK_INS_LIMIT);

        return new CheckInListStatsDTO(
            totalAttendees: $totals->totalAttendeesCount,
            checkedInAttendees: $totals->checkedInCount,
            perProduct: $perProduct->values()->all(),
            recentCheckIns: $recent->values()->all(),
        );
    }
}
