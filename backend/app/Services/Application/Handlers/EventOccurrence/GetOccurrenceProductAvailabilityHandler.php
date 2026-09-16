<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\Constants;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\OccurrenceProductAvailabilityDTO;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\SoldAndReservedQuantitiesService;
use Illuminate\Support\Collection;

class GetOccurrenceProductAvailabilityHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly AvailableProductQuantitiesFetchService $availableProductQuantitiesFetchService,
        private readonly SoldAndReservedQuantitiesService $soldAndReservedQuantities,
    ) {}

    /**
     * @return Collection<OccurrenceProductAvailabilityDTO>
     */
    public function handle(int $eventId, int $occurrenceId): Collection
    {
        $occurrence = $this->occurrenceRepository->findFirstWhere([
            EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
            EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (! $occurrence) {
            throw new ResourceNotFoundException(
                __('Occurrence :id not found for this event', ['id' => $occurrenceId])
            );
        }

        $quantities = $this->availableProductQuantitiesFetchService
            ->getAvailableProductQuantities($eventId, ignoreCache: true, eventOccurrenceId: $occurrenceId)
            ->productQuantities;

        $sold = [];
        foreach ($quantities->pluck('product_type')->unique() as $productType) {
            $sold[$productType] = $this->soldAndReservedQuantities->getSoldByPriceForOccurrence(
                $occurrenceId,
                ProductType::fromName($productType),
            );
        }

        return $quantities->map(fn (AvailableProductQuantitiesDTO $quantity) => new OccurrenceProductAvailabilityDTO(
            product_id: $quantity->product_id,
            product_price_id: $quantity->price_id,
            quantity_sold: $sold[$quantity->product_type][$quantity->price_id] ?? 0,
            quantity_available: $quantity->quantity_available === Constants::INFINITE ? null : $quantity->quantity_available,
        ))->values();
    }
}
