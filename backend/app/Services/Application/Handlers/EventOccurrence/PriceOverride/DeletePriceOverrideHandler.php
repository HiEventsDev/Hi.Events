<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence\PriceOverride;

use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceOccurrenceOverrideDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class DeletePriceOverrideHandler
{
    public function __construct(
        private readonly ProductPriceOccurrenceOverrideRepositoryInterface $overrideRepository,
        private readonly EventOccurrenceRepositoryInterface                $occurrenceRepository,
        private readonly DatabaseManager                                   $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(int $eventId, int $occurrenceId, int $overrideId): void
    {
        $this->databaseManager->transaction(function () use ($eventId, $occurrenceId, $overrideId) {
            $occurrence = $this->occurrenceRepository->findFirstWhere([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

            if (!$occurrence) {
                throw new ResourceNotFoundException(
                    __('Occurrence :id not found for event :eventId', [
                        'id' => $occurrenceId,
                        'eventId' => $eventId,
                    ])
                );
            }

            $override = $this->overrideRepository->findFirstWhere([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::ID => $overrideId,
                ProductPriceOccurrenceOverrideDomainObjectAbstract::EVENT_OCCURRENCE_ID => $occurrenceId,
            ]);

            if (!$override) {
                throw new ResourceNotFoundException(
                    __('Price override :id not found for occurrence :occurrenceId', [
                        'id' => $overrideId,
                        'occurrenceId' => $occurrenceId,
                    ])
                );
            }

            $this->overrideRepository->deleteWhere([
                ProductPriceOccurrenceOverrideDomainObjectAbstract::ID => $overrideId,
            ]);
        });
    }
}
