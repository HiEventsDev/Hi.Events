<?php

namespace TicketKitten\Service\Handler\Event;

use TicketKitten\Http\DataTransferObjects\EventStatsRequestDTO;
use TicketKitten\Http\DataTransferObjects\EventStatsResponseDTO;
use TicketKitten\Service\Common\Event\EventStatsFetchService;

readonly class GetEventStatsHandler
{
    public function __construct(private EventStatsFetchService $eventStatsFetchService)
    {
    }

    public function handle(EventStatsRequestDTO $statsRequestDTO): EventStatsResponseDTO
    {
        return $this->eventStatsFetchService->getEventStats($statsRequestDTO);
    }
}
