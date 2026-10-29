<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use Illuminate\Support\Carbon;

class BadgeReprintService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly BadgeDesignService $badgeDesignService,
    ) {}

    /**
     * @param  int[]  $attendeeIds
     */
    public function markPrinted(int $eventId, array $attendeeIds): int
    {
        if ($attendeeIds === []) {
            return 0;
        }

        return $this->attendeeRepository->incrementEach(
            columns: [AttendeeDomainObjectAbstract::BADGE_PRINT_COUNT => 1],
            additionalUpdates: [AttendeeDomainObjectAbstract::BADGE_PRINTED_AT => Carbon::now()],
            where: [
                AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
                [AttendeeDomainObjectAbstract::ID, 'in', $attendeeIds],
            ],
        );
    }

    /**
     * @param  int[]  $attendeeIds
     */
    public function flagForReprint(array $attendeeIds): void
    {
        if ($attendeeIds === []) {
            return;
        }

        $this->attendeeRepository->updateWhere(
            attributes: [AttendeeDomainObjectAbstract::BADGE_PRINTED_AT => null],
            where: [
                [AttendeeDomainObjectAbstract::ID, 'in', $attendeeIds],
                [AttendeeDomainObjectAbstract::BADGE_PRINTED_AT, 'not null', null],
            ],
        );
    }

    public function flagIfBadgeDetailsChanged(AttendeeDomainObject $before, AttendeeDomainObject $after): AttendeeDomainObject
    {
        $changed = $before->getFirstName() !== $after->getFirstName()
            || $before->getLastName() !== $after->getLastName()
            || $before->getProductId() !== $after->getProductId()
            || $before->getSeatLabel() !== $after->getSeatLabel();

        if (! $changed || $after->getBadgePrintedAt() === null) {
            return $after;
        }

        $this->flagForReprint([$after->getId()]);

        return $after->setBadgePrintedAt(null);
    }

    public function flagForAnswerChange(int $eventId, int $questionId, int $orderId, ?int $attendeeId): void
    {
        if (! in_array($questionId, $this->badgeDesignService->getDesign($eventId)->questionIds(), true)) {
            return;
        }

        $attendeeIds = $attendeeId !== null
            ? [$attendeeId]
            : $this->attendeeRepository
                ->findWhere([AttendeeDomainObjectAbstract::ORDER_ID => $orderId])
                ->map(fn (AttendeeDomainObject $attendee) => $attendee->getId())
                ->all();

        $this->flagForReprint($attendeeIds);
    }
}
