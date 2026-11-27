<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Badge\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Domain\Badge\BadgeDataService;
use HiEvents\Services\Domain\Badge\BadgeDesignService;
use HiEvents\Services\Domain\Badge\CheckInListBadgeAccessService;
use HiEvents\Services\Domain\Badge\DTO\BadgeDTO;

class GetCheckInListAttendeeBadgePublicHandler
{
    public function __construct(
        private readonly CheckInListBadgeAccessService $checkInListBadgeAccessService,
        private readonly BadgeDesignService $badgeDesignService,
        private readonly BadgeDataService $badgeDataService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws CannotCheckInException
     */
    public function handle(string $checkInListShortId, string $attendeePublicId): BadgeDTO
    {
        $attendee = $this->checkInListBadgeAccessService->findPrintableAttendee($checkInListShortId, $attendeePublicId);

        return $this->badgeDataService->buildBadges(
            eventId: $attendee->getEventId(),
            attendees: collect([$attendee]),
            design: $this->badgeDesignService->getDesign($attendee->getEventId()),
        )->first();
    }
}
