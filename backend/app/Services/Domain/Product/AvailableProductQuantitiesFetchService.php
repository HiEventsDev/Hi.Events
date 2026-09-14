<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\Constants;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\DomainObjects\Status\CapacityAssignmentStatus;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class AvailableProductQuantitiesFetchService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Config $config,
        private readonly Cache $cache,
        private readonly CapacityAssignmentRepositoryInterface $capacityAssignmentRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly ProductPriceOccurrenceOverrideRepositoryInterface $priceOverrideRepository,
        private readonly SoldAndReservedQuantitiesService $soldAndReservedQuantities,
    ) {}

    public function getAvailableProductQuantities(
        int $eventId,
        bool $ignoreCache = false,
        ?int $eventOccurrenceId = null,
        bool $applyOccurrenceLimits = true,
    ): AvailableProductQuantitiesResponseDTO {
        if (! $ignoreCache && $eventOccurrenceId === null && $this->config->get('app.homepage_product_quantities_cache_ttl')) {
            $cachedData = $this->getDataFromCache($eventId);
            if ($cachedData) {
                return $cachedData;
            }
        }

        $event = $this->eventRepository->findById($eventId);
        $isRecurring = $event !== null && $event->isRecurring();

        $capacities = collect();
        if (! $isRecurring) {
            $capacities = $this->capacityAssignmentRepository
                ->loadRelation(ProductDomainObject::class)
                ->findWhere([
                    'event_id' => $eventId,
                    'applies_to' => CapacityAssignmentAppliesTo::PRODUCTS->name,
                    'status' => CapacityAssignmentStatus::ACTIVE->name,
                ]);
        }

        $productCapacities = $this->calculateProductCapacities($capacities);

        $reservedProductQuantities = $this->fetchProductQuantities($eventId);

        $quantities = $reservedProductQuantities->map(function (AvailableProductQuantitiesDTO $dto) use ($productCapacities) {
            $productId = $dto->product_id;
            if (isset($productCapacities[$productId])) {
                $dto->quantity_available = min(array_merge([$dto->quantity_available], $productCapacities[$productId]->map->getAvailableCapacity()->toArray()));
                $dto->capacities = $productCapacities[$productId];
            }

            return $dto;
        });

        $occurrence = null;
        $occurrenceReserved = null;
        if ($eventOccurrenceId !== null) {
            $occurrence = $this->occurrenceRepository->findById($eventOccurrenceId);
            if ($isRecurring) {
                $quantities = $this->applyPerOccurrenceQuantities($quantities, $eventId, $eventOccurrenceId);
            }
            if ($applyOccurrenceLimits) {
                if ($this->occurrenceLimitsCapacity($occurrence)) {
                    $occurrenceReserved = $this->soldAndReservedQuantities->getReservedTicketsForOccurrence($eventOccurrenceId);
                }
                $quantities = $this->applyOccurrenceCapacity($quantities, $occurrence, $occurrenceReserved);
            }
        } elseif ($isRecurring) {
            $quantities = $this->ignorePerOccurrenceQuantities($quantities);
        }

        $finalData = new AvailableProductQuantitiesResponseDTO(
            productQuantities: $quantities,
            capacities: $capacities,
            occurrence: $occurrence,
            occurrenceReservedQuantity: $occurrenceReserved,
        );

        if (! $ignoreCache && $eventOccurrenceId === null && $this->config->get('app.homepage_product_quantities_cache_ttl')) {
            $this->cache->put($this->getCacheKey($eventId), $finalData, $this->config->get('app.homepage_product_quantities_cache_ttl'));
        }

        return $finalData;
    }

    private function applyPerOccurrenceQuantities(Collection $quantities, int $eventId, int $eventOccurrenceId): Collection
    {
        $perOccurrenceRows = $quantities->filter(
            fn (AvailableProductQuantitiesDTO $dto) => $dto->quantity_applies_to === ProductQuantityAppliesTo::OCCURRENCE->name
        );

        if ($perOccurrenceRows->isEmpty()) {
            return $quantities;
        }

        $overrides = $this->priceOverrideRepository
            ->findWhere([ProductPriceOccurrenceOverrideDomainObject::EVENT_OCCURRENCE_ID => $eventOccurrenceId])
            ->keyBy(fn (ProductPriceOccurrenceOverrideDomainObject $override) => $override->getProductPriceId());

        $reserved = $this->soldAndReservedQuantities->getReservedByPrice($eventId, $eventOccurrenceId);

        $sold = [];
        foreach ($perOccurrenceRows->pluck('product_type')->unique() as $productType) {
            $sold[$productType] = $this->soldAndReservedQuantities->getSoldByPriceForOccurrence(
                $eventOccurrenceId,
                ProductType::fromName($productType),
            );
        }

        return $quantities->map(function (AvailableProductQuantitiesDTO $dto) use ($overrides, $reserved, $sold) {
            if ($dto->quantity_applies_to !== ProductQuantityAppliesTo::OCCURRENCE->name) {
                return $dto;
            }

            /** @var ProductPriceOccurrenceOverrideDomainObject|null $override */
            $override = $overrides->get($dto->price_id);
            $cap = $override?->getQuantityAvailable() ?? $dto->initial_quantity_available;

            if ($cap === null) {
                $dto->quantity_available = Constants::INFINITE;
                $dto->quantity_reserved = $reserved[$dto->price_id] ?? 0;

                return $dto;
            }

            $dto->quantity_reserved = $reserved[$dto->price_id] ?? 0;
            $dto->quantity_available = max(0, $cap - ($sold[$dto->product_type][$dto->price_id] ?? 0) - $dto->quantity_reserved);

            return $dto;
        });
    }

    private function ignorePerOccurrenceQuantities(Collection $quantities): Collection
    {
        return $quantities->map(function (AvailableProductQuantitiesDTO $dto) {
            if ($dto->quantity_applies_to === ProductQuantityAppliesTo::OCCURRENCE->name) {
                $dto->quantity_available = Constants::INFINITE;
            }

            return $dto;
        });
    }

    private function occurrenceLimitsCapacity(?EventOccurrenceDomainObject $occurrence): bool
    {
        return $occurrence !== null
            && ! $occurrence->isCancelled()
            && ! $occurrence->isPast()
            && $occurrence->getCapacity() !== null;
    }

    private function applyOccurrenceCapacity(
        Collection $quantities,
        ?EventOccurrenceDomainObject $occurrence,
        ?int $reservedForOccurrence,
    ): Collection {
        if ($occurrence === null || $occurrence->isCancelled() || $occurrence->isPast()) {
            return $quantities->map(function (AvailableProductQuantitiesDTO $dto) {
                $dto->quantity_available = 0;

                return $dto;
            });
        }

        if ($occurrence->getCapacity() === null) {
            return $quantities;
        }

        $occurrenceAvailable = max(0, $occurrence->getCapacity() - $occurrence->getUsedCapacity() - $reservedForOccurrence);

        return $quantities->map(function (AvailableProductQuantitiesDTO $dto) use ($occurrenceAvailable) {
            if ($dto->product_type !== ProductType::TICKET->name) {
                return $dto;
            }

            if ($dto->quantity_available !== Constants::INFINITE) {
                $dto->quantity_available = min($dto->quantity_available, $occurrenceAvailable);
            } else {
                $dto->quantity_available = $occurrenceAvailable;
            }

            return $dto;
        });
    }

    private function fetchProductQuantities(int $eventId): Collection
    {
        $rows = $this->db->select(<<<'SQL'
        SELECT
            products.id AS product_id,
            product_prices.id AS product_price_id,
            products.title AS product_title,
            products.product_type AS product_type,
            product_prices.label AS price_label,
            product_prices.initial_quantity_available,
            product_prices.quantity_sold,
            product_prices.quantity_applies_to
        FROM products
        JOIN product_prices ON products.id = product_prices.product_id
        WHERE
            products.event_id = :eventId
            AND products.deleted_at IS NULL
            AND product_prices.deleted_at IS NULL
    SQL, ['eventId' => $eventId]);

        $reserved = $this->soldAndReservedQuantities->getReservedByPrice($eventId);

        return collect($rows)->map(function ($row) use ($reserved) {
            $quantityReserved = $reserved[$row->product_price_id] ?? 0;

            return AvailableProductQuantitiesDTO::fromArray([
                'product_id' => $row->product_id,
                'price_id' => $row->product_price_id,
                'product_title' => $row->product_title,
                'product_type' => $row->product_type,
                'quantity_applies_to' => $row->quantity_applies_to,
                'price_label' => $row->price_label,
                'quantity_available' => $row->initial_quantity_available === null
                    ? Constants::INFINITE
                    : max(0, $row->initial_quantity_available - $row->quantity_sold - $quantityReserved),
                'initial_quantity_available' => $row->initial_quantity_available,
                'quantity_reserved' => $quantityReserved,
                'capacities' => new Collection,
            ]);
        });
    }

    /**
     * @param  Collection<CapacityAssignmentDomainObject>  $capacities
     */
    private function calculateProductCapacities(Collection $capacities): array
    {
        $productCapacities = [];
        foreach ($capacities as $capacity) {
            foreach ($capacity->getProducts() as $product) {
                $productId = $product->getId();
                if (! isset($productCapacities[$productId])) {
                    $productCapacities[$productId] = collect();
                }

                $productCapacities[$productId]->push($capacity);
            }
        }

        return $productCapacities;
    }

    private function getDataFromCache(int $eventId): ?AvailableProductQuantitiesResponseDTO
    {
        return $this->cache->get($this->getCacheKey($eventId));
    }

    private function getCacheKey(int $eventId): string
    {
        return "event.$eventId.available_product_quantities";
    }
}
