<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Badge;

use HiEvents\Repository\DTO\BadgeAttendeeQueryDTO;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Badge\DTO\GetBadgesDTO;
use HiEvents\Services\Domain\Badge\BadgeDataService;
use HiEvents\Services\Domain\Badge\BadgeDesignService;
use HiEvents\Services\Domain\Badge\BadgeEligibilityService;
use Illuminate\Pagination\LengthAwarePaginator;

class GetBadgesHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly BadgeDesignService $badgeDesignService,
        private readonly BadgeDataService $badgeDataService,
        private readonly BadgeEligibilityService $badgeEligibilityService,
    ) {}

    public function handle(GetBadgesDTO $dto): LengthAwarePaginator
    {
        $attendees = $this->attendeeRepository->findForBadges(new BadgeAttendeeQueryDTO(
            event_id: $dto->event_id,
            include_awaiting_payment: $this->badgeEligibilityService->includesAwaitingPayment($dto->event_id),
            attendee_ids: $dto->attendee_ids,
            order_id: $dto->order_id,
            product_ids: $dto->product_ids,
            event_occurrence_id: $dto->event_occurrence_id,
            only_unprinted: $dto->only_unprinted,
            sort_by: $dto->sort_by,
            page: $dto->page,
            per_page: $dto->per_page,
        ));

        $badges = $this->badgeDataService->buildBadges(
            eventId: $dto->event_id,
            attendees: $attendees->getCollection(),
            design: $this->badgeDesignService->getDesign($dto->event_id),
        );

        return $attendees->setCollection($badges);
    }
}
