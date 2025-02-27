<?php

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicOrganizerEventsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class GetPublicEventsHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
    )
    {
    }

    public function handle(GetPublicOrganizerEventsDTO $dto): LengthAwarePaginator
    {
        return $this->eventRepository->findEvents(
            where: [
                'organizer_id' => $dto->organizerId,
                'status' => EventStatus::LIVE->name,
            ],
            params: $dto->queryParams
        );
    }
}
