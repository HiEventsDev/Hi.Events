<?php

namespace TicketKitten\Service\Handler\Event;

use TicketKitten\Service\Common\Event\DTO\EventCheckInStatsResponseDTO;
use TicketKitten\Service\Common\Event\EventStatsFetchService;

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
