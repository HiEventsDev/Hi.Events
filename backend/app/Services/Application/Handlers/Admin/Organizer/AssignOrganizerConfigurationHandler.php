<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\Organizer;

use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssignOrganizerConfigurationHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface              $organizerRepository,
        private readonly OrganizerConfigurationRepositoryInterface $configurationRepository,
    )
    {
    }

    /**
     * @throws ModelNotFoundException
     */
    public function handle(int $organizerId, int $configurationId): void
    {
        $this->configurationRepository->findById($configurationId);

        $this->organizerRepository->updateFromArray(
            id: $organizerId,
            attributes: ['organizer_configuration_id' => $configurationId],
        );
    }
}
