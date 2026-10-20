<?php

namespace Tests\Unit\Services\Application\Handlers\EventOccurrence;

use HiEvents\Constants;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\GetOccurrenceProductAvailabilityHandler;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Product\SoldAndReservedQuantitiesService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetOccurrenceProductAvailabilityHandlerTest extends TestCase
{
    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private AvailableProductQuantitiesFetchService|MockInterface $fetchService;

    private SoldAndReservedQuantitiesService|MockInterface $soldAndReserved;

    private GetOccurrenceProductAvailabilityHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->fetchService = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $this->soldAndReserved = Mockery::mock(SoldAndReservedQuantitiesService::class);
        $this->handler = new GetOccurrenceProductAvailabilityHandler(
            $this->occurrenceRepository,
            $this->fetchService,
            $this->soldAndReserved,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_pairs_per_date_sold_and_available_for_each_price(): void
    {
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn(Mockery::mock(EventOccurrenceDomainObject::class));
        $this->fetchService->shouldReceive('getAvailableProductQuantities')
            ->once()
            ->with(1, true, 10)
            ->andReturn(new AvailableProductQuantitiesResponseDTO(productQuantities: collect([
                $this->quantity(100, 11, ProductType::TICKET, 4),
                $this->quantity(200, 21, ProductType::GENERAL, Constants::INFINITE),
            ])));
        $this->soldAndReserved->shouldReceive('getSoldByPriceForOccurrence')->once()->with(10, ProductType::TICKET)->andReturn([11 => 1]);
        $this->soldAndReserved->shouldReceive('getSoldByPriceForOccurrence')->once()->with(10, ProductType::GENERAL)->andReturn([]);

        $result = $this->handler->handle(1, 10);

        $this->assertSame([
            ['product_id' => 100, 'product_price_id' => 11, 'quantity_sold' => 1, 'quantity_available' => 4],
            ['product_id' => 200, 'product_price_id' => 21, 'quantity_sold' => 0, 'quantity_available' => null],
        ], $result->map(fn ($dto) => $dto->toArray())->all());
    }

    public function test_it_rejects_an_occurrence_from_another_event(): void
    {
        $this->occurrenceRepository->shouldReceive('findFirstWhere')->once()->andReturn(null);
        $this->fetchService->shouldNotReceive('getAvailableProductQuantities');

        $this->expectException(ResourceNotFoundException::class);
        $this->handler->handle(1, 10);
    }

    private function quantity(int $productId, int $priceId, ProductType $type, int $available): AvailableProductQuantitiesDTO
    {
        return AvailableProductQuantitiesDTO::fromArray([
            'product_id' => $productId,
            'price_id' => $priceId,
            'product_title' => 'P',
            'price_label' => null,
            'quantity_available' => $available,
            'quantity_reserved' => 0,
            'initial_quantity_available' => null,
            'product_type' => $type->name,
            'quantity_applies_to' => 'OCCURRENCE',
        ]);
    }
}
