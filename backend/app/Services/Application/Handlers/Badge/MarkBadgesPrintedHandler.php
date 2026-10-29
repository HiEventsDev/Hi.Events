<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Badge;

use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\Badge\BadgeReprintService;

class MarkBadgesPrintedHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly BadgeReprintService $badgeReprintService,
    ) {}

    /**
     * @param  int[]  $attendeeIds
     *
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, array $attendeeIds): int
    {
        $matched = $this->attendeeRepository->countWhere([
            AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
            [AttendeeDomainObjectAbstract::ID, 'in', $attendeeIds],
        ]);

        if ($matched !== count($attendeeIds)) {
            throw new ResourceNotFoundException(__('One or more attendees could not be found for this event.'));
        }

        return $this->badgeReprintService->markPrinted($eventId, $attendeeIds);
    }
}
