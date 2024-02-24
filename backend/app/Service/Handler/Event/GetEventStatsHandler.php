<?php

namespace HiEvents\Service\Handler\Event;

use HiEvents\Http\DataTransferObjects\EventStatsRequestDTO;
use HiEvents\Http\DataTransferObjects\EventStatsResponseDTO;
use HiEvents\Service\Common\Event\EventStatsFetchService;

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
