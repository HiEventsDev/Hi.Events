<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers\Public;

use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\PublicEventSeatMapDTO;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;

class GetEventSeatMapPublicHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSeatMapLookupService $lookupService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId): PublicEventSeatMapDTO
    {
        return new PublicEventSeatMapDTO(
            event: $this->eventRepository->findById($eventId),
            eventSeatMap: $this->lookupService->getForEvent($eventId),
        );
    }
}
