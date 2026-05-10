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

    public function handle(string $shortId, ?int $clientOccurrenceFilter = null): CheckInListStatsDTO
    {
        $checkInList = $this->checkInListRepository->findFirstWhere(['short_id' => $shortId]);

        if (!$checkInList) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        // Scoped lists ignore the client filter (the list already owns an
        // occurrence). Unscoped lists honour the filter pill.
        $effectiveOverride = $checkInList->getEventOccurrenceId() !== null
            ? null
            : $clientOccurrenceFilter;

        $totals = $this->checkInListRepository->getCheckedInAttendeeCountById($checkInList->getId(), $effectiveOverride);
        $perProduct = $this->checkInListRepository->getPerProductCheckInStatsById($checkInList->getId(), $effectiveOverride);
        $recent = $this->checkInListRepository->getRecentCheckInsById($checkInList->getId(), self::RECENT_CHECK_INS_LIMIT, $effectiveOverride);

        return new CheckInListStatsDTO(
            totalAttendees: $totals->totalAttendeesCount,
            checkedInAttendees: $totals->checkedInCount,
            perProduct: $perProduct->values()->all(),
            recentCheckIns: $recent->values()->all(),
        );
    }
}
