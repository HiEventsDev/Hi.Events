<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Location;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;

class LocationOwnershipValidator
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function assertOwnedBy(?int $locationId, int $organizerId, int $accountId): void
    {
        if ($locationId === null) {
            return;
        }

        $location = $this->locationRepository->findFirstWhere([
            'id' => $locationId,
            'account_id' => $accountId,
            'organizer_id' => $organizerId,
        ]);

        if ($location === null) {
            throw new ResourceNotFoundException(
                __('Location :id not found', ['id' => $locationId]),
            );
        }
    }
}
