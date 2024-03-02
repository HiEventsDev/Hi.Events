<?php

namespace HiEvents\Services\Handlers\Event;

use HiEvents\Services\Common\Event\DTO\EventCheckInStatsResponseDTO;
use HiEvents\Services\Common\Event\EventStatsFetchService;

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
