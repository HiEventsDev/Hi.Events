<?php

namespace HiEvents\Service\Handler\Event;

use HiEvents\Service\Common\Event\DTO\EventCheckInStatsResponseDTO;
use HiEvents\Service\Common\Event\EventStatsFetchService;

readonly class GetEventCheckInStatsHandler
{
    public function __construct(private EventStatsFetchService $eventStatsFetchService)
    {
    }

    public function handle(int $eventId): EventCheckInStatsResponseDTO
    {
        return $this->eventStatsFetchService->getCheckedInStats($eventId);
    }
}
