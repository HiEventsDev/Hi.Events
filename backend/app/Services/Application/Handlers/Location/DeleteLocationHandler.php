<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Location;

use HiEvents\DomainObjects\Generated\LocationDomainObjectAbstract;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class DeleteLocationHandler
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(int $organizerId, int $accountId, int $locationId): void
    {
        $this->databaseManager->transaction(function () use ($organizerId, $accountId, $locationId) {
            $location = $this->locationRepository->findFirstWhere([
                LocationDomainObjectAbstract::ID => $locationId,
                LocationDomainObjectAbstract::ORGANIZER_ID => $organizerId,
                LocationDomainObjectAbstract::ACCOUNT_ID => $accountId,
            ]);

            if ($location === null) {
                throw new ResourceNotFoundException(__('Location not found'));
            }

            if ($this->locationRepository->isReferenced($locationId)) {
                throw new ResourceConflictException(
                    __('This location is referenced by one or more events or occurrences and cannot be deleted')
                );
            }

            $this->locationRepository->deleteWhere([
                LocationDomainObjectAbstract::ID => $locationId,
            ]);
        });
    }
}
