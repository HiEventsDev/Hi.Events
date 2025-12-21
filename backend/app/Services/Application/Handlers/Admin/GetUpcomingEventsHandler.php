<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetUpcomingEventsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetUpcomingEventsHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
    )
    {
    }

    public function handle(GetUpcomingEventsDTO $dto): LengthAwarePaginator
    {
        return $this->eventRepository->getUpcomingEventsForAdmin($dto->perPage);
    }
}
