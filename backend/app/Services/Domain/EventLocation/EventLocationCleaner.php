<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventLocation;

use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;

class EventLocationCleaner
{
    public function __construct(
        private readonly EventLocationRepositoryInterface $eventLocationRepository,
    ) {}

    public function deleteIfOrphaned(?int $eventLocationId): void
    {
        if ($eventLocationId === null) {
            return;
        }

        if ($this->eventLocationRepository->isReferenced($eventLocationId)) {
            return;
        }

        $this->eventLocationRepository->deleteById($eventLocationId);
    }
}
