<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;

class DeleteConfigurationHandler
{
    public function __construct(
        private readonly OrganizerConfigurationRepositoryInterface $repository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
    ) {}

    /**
     * @throws CannotDeleteEntityException
     */
    public function handle(int $configurationId): void
    {
        $configuration = $this->repository->findById($configurationId);

        if ($configuration->isDefault()) {
            throw new CannotDeleteEntityException(
                __('Default configurations cannot be deleted.')
            );
        }

        $assignedOrganizerCount = $this->organizerRepository->countWhere([
            'organizer_configuration_id' => $configurationId,
        ]);

        if ($assignedOrganizerCount > 0) {
            throw new CannotDeleteEntityException(
                __('This plan is still assigned to :count organizer(s). Reassign them before deleting it.', [
                    'count' => $assignedOrganizerCount,
                ])
            );
        }

        $this->repository->deleteById($configurationId);
    }
}
