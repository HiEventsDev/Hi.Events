<?php

namespace HiEvents\Services\Domain\Organizer;

use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class GetOrganizerStatsService
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private DatabaseManager                       $db,
    )
    {
    }

    public function getOrganizerStats(int $organizerId, string $startDate, string $endDate): array
    {
        return $this->db->transaction(function () use ($organizerId, $startDate, $endDate) {
            return $this->organizerRepository->getOrganizerStats($organizerId, $startDate, $endDate);
        });
    }
}
