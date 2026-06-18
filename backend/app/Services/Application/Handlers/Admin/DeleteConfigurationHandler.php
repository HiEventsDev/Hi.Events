<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class DeleteConfigurationHandler
{
    public function __construct(
        private readonly OrganizerConfigurationRepositoryInterface $repository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws CannotDeleteEntityException
     * @throws Throwable
     */
    public function handle(int $configurationId): void
    {
        $configuration = $this->repository->findById($configurationId);

        if ($configuration->getIsSystemDefault()) {
            throw new CannotDeleteEntityException(
                __('The system default configuration cannot be deleted.')
            );
        }

        $referencingOrganizers = $this->organizerRepository->countWhere([
            'organizer_configuration_id' => $configurationId,
        ]);

        if ($referencingOrganizers > 0) {
            throw new CannotDeleteEntityException(
                __('This configuration is assigned to :count organizer(s). Reassign them before deleting it.', [
                    'count' => $referencingOrganizers,
                ])
            );
        }

        $this->databaseManager->transaction(function () use ($configurationId) {
            $this->repository->updateWhere(
                attributes: ['upgrades_to_id' => null],
                where: ['upgrades_to_id' => $configurationId],
            );

            $this->repository->deleteById($configurationId);
        });
    }
}
