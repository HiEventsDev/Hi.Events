<?php

namespace HiEvents\Services\Handlers\Event;

use HiEvents\Services\Domain\Event\EventStatsFetchService;
use HiEvents\Services\Handlers\Event\DTO\EventStatsRequestDTO;
use HiEvents\Services\Handlers\Event\DTO\EventStatsResponseDTO;

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
