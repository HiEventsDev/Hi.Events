<?php

namespace Tests\Unit\Services\Domain\Product\DTO;

use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use Tests\TestCase;

class AvailableProductQuantitiesResponseDTOTest extends TestCase
{
    private function quantities(int $productId, int $reserved): AvailableProductQuantitiesDTO
    {
        return AvailableProductQuantitiesDTO::fromArray([
            'product_id' => $productId,
            'price_id' => $productId * 10,
            'product_title' => 'T',
            'product_type' => ProductType::TICKET->name,
            'price_label' => null,
            'quantity_available' => 100,
            'initial_quantity_available' => null,
            'quantity_reserved' => $reserved,
        ]);
    }

    private function response(?int $poolCapacity, int $poolUsed, ?EventOccurrenceDomainObject $occurrence = null, ?int $occurrenceReserved = null): AvailableProductQuantitiesResponseDTO
    {
        $pool = (new CapacityAssignmentDomainObject)
            ->setCapacity($poolCapacity)
            ->setUsedCapacity($poolUsed)
            ->setProducts(collect([(new ProductDomainObject)->setId(1), (new ProductDomainObject)->setId(2)]));

        return new AvailableProductQuantitiesResponseDTO(
            productQuantities: collect([$this->quantities(1, 1), $this->quantities(2, 1), $this->quantities(3, 50)]),
            capacities: collect([$pool]),
            occurrence: $occurrence,
            occurrenceReservedQuantity: $occurrenceReserved,
        );
    }

    public function test_a_pool_is_filled_by_the_sum_of_its_products_and_reserved_orders(): void
    {
        $response = $this->response(poolCapacity: 10, poolUsed: 4);

        $this->assertSame(4, $response->remainingPoolCapacity($response->capacities->first()));
        $this->assertNull($response->firstOverflowingPool([1 => 2, 2 => 2, 3 => 40]));
        $this->assertSame($response->capacities->first(), $response->firstOverflowingPool([1 => 3, 2 => 2]));
    }

    public function test_an_unlimited_pool_never_overflows(): void
    {
        $this->assertNull($this->response(poolCapacity: null, poolUsed: 400)->firstOverflowingPool([1 => 99]));
    }

    public function test_date_capacity_leaves_room_for_reserved_tickets(): void
    {
        $occurrence = (new EventOccurrenceDomainObject)->setCapacity(10)->setUsedCapacity(6);

        $this->assertSame(1, $this->response(null, 0, $occurrence, 3)->remainingOccurrenceCapacity());
        $this->assertNull($this->response(null, 0, (new EventOccurrenceDomainObject)->setCapacity(null), null)->remainingOccurrenceCapacity());
    }
}
