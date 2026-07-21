<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\DTO\CheckInListStatsDTO;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetCheckInListStatsPublicHandler
{
    private const RECENT_CHECK_INS_LIMIT = 20;

    public function __construct(
        private readonly CheckInListRepositoryInterface $checkInListRepository,
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

        $this->validateCheckInListIsActive($checkInList);

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

    /**
     * @throws CannotCheckInException
     */
    private function validateCheckInListIsActive(CheckInListDomainObject $checkInList): void
    {
        if ($checkInList->getExpiresAt() && DateHelper::utcDateIsPast($checkInList->getExpiresAt())) {
            throw new CannotCheckInException(__('Check-in list has expired'));
        }

        if ($checkInList->getActivatesAt() && DateHelper::utcDateIsFuture($checkInList->getActivatesAt())) {
            throw new CannotCheckInException(__('Check-in list is not active yet'));
        }
    }
}
