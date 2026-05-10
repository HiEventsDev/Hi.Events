<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductOccurrenceVisibilityDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;
use Illuminate\Support\Collection;

class GetProductVisibilityHandler
{
    public function __construct(
        private readonly ProductOccurrenceVisibilityRepositoryInterface $visibilityRepository,
        private readonly EventOccurrenceRepositoryInterface             $occurrenceRepository,
    )
    {
    }

    public function handle(int $eventId, int $occurrenceId): Collection
    {
        $occurrence = $this->occurrenceRepository->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (!$occurrence) {
            throw new ResourceNotFoundException(
                __('Occurrence :id not found for this event', ['id' => $occurrenceId])
            );
        }

        return $this->visibilityRepository->findWhere([
            ProductOccurrenceVisibilityDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
        ]);
    }
}
