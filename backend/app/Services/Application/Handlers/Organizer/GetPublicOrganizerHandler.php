<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;

class GetPublicOrganizerHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository
    )
    {
    }

    public function handle(int $organizerId)
    {
        return $this->organizerRepository->findById($organizerId);
    }
}
