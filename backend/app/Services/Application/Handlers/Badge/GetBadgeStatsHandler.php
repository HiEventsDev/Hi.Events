<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Badge;

use HiEvents\Repository\DTO\BadgeStatsDTO;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\Badge\BadgeEligibilityService;

class GetBadgeStatsHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly BadgeEligibilityService $badgeEligibilityService,
    ) {}

    public function handle(int $eventId, ?int $eventOccurrenceId): BadgeStatsDTO
    {
        return $this->attendeeRepository->getBadgeStats(
            eventId: $eventId,
            includeAwaitingPayment: $this->badgeEligibilityService->includesAwaitingPayment($eventId),
            eventOccurrenceId: $eventOccurrenceId,
        );
    }
}
