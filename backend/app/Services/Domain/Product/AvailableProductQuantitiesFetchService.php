<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\Constants;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\DomainObjects\Status\CapacityAssignmentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class AvailableProductQuantitiesFetchService
{
    public function __construct(
        private readonly DatabaseManager                       $db,
        private readonly Config                                $config,
        private readonly Cache                                 $cache,
        private readonly CapacityAssignmentRepositoryInterface $capacityAssignmentRepository,
    )
    {
    }

    public function getAvailableProductQuantities(int $eventId, bool $ignoreCache = false): AvailableProductQuantitiesResponseDTO
    {
        if (!$ignoreCache && $this->config->get('app.homepage_product_quantities_cache_ttl')) {
            $cachedData = $this->getDataFromCache($eventId);
            if ($cachedData) {
                return $cachedData;
            }
        }

        $capacities = $this->capacityAssignmentRepository
            ->loadRelation(ProductDomainObject::class)
            ->findWhere([
                'event_id' => $eventId,
                'applies_to' => CapacityAssignmentAppliesTo::PRODUCTS->name,
                'status' => CapacityAssignmentStatus::ACTIVE->name,
            ]);

        $reservedProductQuantities = $this->fetchReservedProductQuantities($eventId);
        $productCapacities = $this->calculateProductCapacities($capacities);

        $quantities = $reservedProductQuantities->map(function (AvailableProductQuantitiesDTO $dto) use ($productCapacities) {
            $productId = $dto->product_id;
            if (isset($productCapacities[$productId])) {
                $dto->quantity_available = min(array_merge([$dto->quantity_available], $productCapacities[$productId]->map->getAvailableCapacity()->toArray()));
                $dto->capacities = $productCapacities[$productId];
            }

            return $dto;
        });

        $finalData = new AvailableProductQuantitiesResponseDTO(
            productQuantities: $quantities,
            capacities: $capacities
        );

        if (!$ignoreCache && $this->config->get('app.homepage_product_quantities_cache_ttl')) {
            $this->cache->put($this->getCacheKey($eventId), $finalData, $this->config->get('app.homepage_product_quantities_cache_ttl'));
        }

        return $finalData;
    }

    private function fetchReservedProductQuantities(int $eventId): Collection
    {
        $result = $this->db->select(<<<SQL
        WITH reserved_quantities AS (
            SELECT
                products.id AS product_id,
                product_prices.id AS product_price_id,
                SUM(
                    CASE
                        WHEN orders.status = :reserved
                             AND orders.reserved_until > NOW()
                             AND orders.deleted_at IS NULL
                        THEN order_items.quantity
                        ELSE 0
                    END
                ) AS quantity_reserved
            FROM products
            JOIN product_prices ON products.id = product_prices.product_id
            LEFT JOIN order_items ON order_items.product_id = products.id
                AND order_items.product_price_id = product_prices.id
            LEFT JOIN orders ON orders.id = order_items.order_id
                AND orders.event_id = products.event_id
                AND orders.deleted_at IS NULL
            WHERE
                products.event_id = :eventId
                AND products.deleted_at IS NULL
                AND product_prices.deleted_at IS NULL
            GROUP BY products.id, product_prices.id
        )
        SELECT
            products.id AS product_id,
            product_prices.id AS product_price_id,
            products.title AS product_title,
            product_prices.label AS price_label,
            product_prices.initial_quantity_available,
            product_prices.quantity_sold,
            COALESCE(
                product_prices.initial_quantity_available
                - product_prices.quantity_sold
                - COALESCE(reserved_quantities.quantity_reserved, 0),
            0) AS quantity_available,
            COALESCE(reserved_quantities.quantity_reserved, 0) AS quantity_reserved,
            CASE WHEN product_prices.initial_quantity_available IS NULL
                THEN TRUE
                ELSE FALSE
                END AS unlimited_quantity_available
        FROM products
        JOIN product_prices ON products.id = product_prices.product_id
        LEFT JOIN reserved_quantities ON products.id = reserved_quantities.product_id
            AND product_prices.id = reserved_quantities.product_price_id
        WHERE
            products.event_id = :eventId
            AND products.deleted_at IS NULL
            AND product_prices.deleted_at IS NULL
        GROUP BY products.id, product_prices.id, reserved_quantities.quantity_reserved;
    SQL, [
            'eventId' => $eventId,
            'reserved' => OrderStatus::RESERVED->name
        ]);

        return collect($result)->map(fn($row) => AvailableProductQuantitiesDTO::fromArray([
            'product_id' => $row->product_id,
            'price_id' => $row->product_price_id,
            'product_title' => $row->product_title,
            'price_label' => $row->price_label,
            'quantity_available' => $row->unlimited_quantity_available ? Constants::INFINITE : $row->quantity_available,
            'initial_quantity_available' => $row->initial_quantity_available,
            'quantity_reserved' => $row->quantity_reserved,
            'capacities' => new Collection(),
        ]));
    }

    /**
     * @param Collection<CapacityAssignmentDomainObject> $capacities
     */
    private function calculateProductCapacities(Collection $capacities): array
    {
        $productCapacities = [];
        foreach ($capacities as $capacity) {
            foreach ($capacity->getProducts() as $product) {
                $productId = $product->getId();
                if (!isset($productCapacities[$productId])) {
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
