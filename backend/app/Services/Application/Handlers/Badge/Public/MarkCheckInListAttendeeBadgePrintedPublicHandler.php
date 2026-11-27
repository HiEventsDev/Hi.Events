<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Badge\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Domain\Badge\BadgeReprintService;
use HiEvents\Services\Domain\Badge\CheckInListBadgeAccessService;
use HiEvents\Services\Domain\Badge\DTO\BadgeDTO;

class MarkCheckInListAttendeeBadgePrintedPublicHandler
{
    public function __construct(
        private readonly CheckInListBadgeAccessService $checkInListBadgeAccessService,
        private readonly BadgeReprintService $badgeReprintService,
        private readonly GetCheckInListAttendeeBadgePublicHandler $getBadgeHandler,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws CannotCheckInException
     */
    public function handle(string $checkInListShortId, string $attendeePublicId): BadgeDTO
    {
        $attendee = $this->checkInListBadgeAccessService->findPrintableAttendee($checkInListShortId, $attendeePublicId);

        $this->badgeReprintService->markPrinted($attendee->getEventId(), [$attendee->getId()]);

        return $this->getBadgeHandler->handle($checkInListShortId, $attendeePublicId);
    }
}
