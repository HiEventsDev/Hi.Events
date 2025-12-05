<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;

class DeleteConfigurationHandler
{
    public function __construct(
        private readonly AccountConfigurationRepositoryInterface $repository,
    ) {
    }

    /**
     * @throws CannotDeleteEntityException
     */
    public function handle(int $configurationId): void
    {
        $configuration = $this->repository->findById($configurationId);

        if ($configuration->getIsSystemDefault()) {
            throw new CannotDeleteEntityException(
                __('The system default configuration cannot be deleted.')
            );
        }

        $this->repository->deleteById($configurationId);
    }
}
