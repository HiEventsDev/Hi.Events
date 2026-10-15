<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\Generated\EventSpaceDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;

class DeleteEventSpaceHandler
{
    public function __construct(
        private readonly EventSpaceRepositoryInterface $spaceRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $spaceId): void
    {
        $deleted = $this->spaceRepository->deleteWhere([
            EventSpaceDomainObjectAbstract::ID => $spaceId,
            EventSpaceDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($deleted === 0) {
            throw new ResourceNotFoundException(__('Stage or room not found'));
        }
    }
}
