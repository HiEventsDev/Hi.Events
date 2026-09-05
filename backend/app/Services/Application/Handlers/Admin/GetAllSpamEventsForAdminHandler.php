<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllSpamEventsForAdminHandler
{
    public function __construct(
        private readonly EventSpamCheckRepositoryInterface $eventSpamCheckRepository,
    ) {}

    public function handle(?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->eventSpamCheckRepository->getFlaggedEventsForAdmin($search, $perPage);
    }
}
