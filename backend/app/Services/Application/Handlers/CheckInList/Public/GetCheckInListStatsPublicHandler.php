<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\DTO\CheckInListStatsDTO;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Domain\CheckInList\CheckInListActivityValidator;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCheckInListStatsPublicHandler
{
    private const RECENT_CHECK_INS_LIMIT = 20;

    public function __construct(
        private readonly CheckInListRepositoryInterface $checkInListRepository,
        private readonly CheckInListActivityValidator $checkInListActivityValidator,
    ) {}

    /**
     * @throws CannotCheckInException
     */
    public function handle(string $shortId, ?int $clientOccurrenceFilter = null): CheckInListStatsDTO
    {
        $checkInList = $this->checkInListRepository->findFirstWhere(['short_id' => $shortId]);

        if (! $checkInList) {
            throw new ResourceNotFoundException(__('Check-in list not found'));
        }

        $this->checkInListActivityValidator->assertActive($checkInList);

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
