<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderCreateRequestValidationService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderCreateRequestValidationServiceTest extends TestCase
{
    private ProductRepositoryInterface|MockInterface $productRepository;
    private PromoCodeRepositoryInterface|MockInterface $promoCodeRepository;
    private EventRepositoryInterface|MockInterface $eventRepository;
    private AvailableProductQuantitiesFetchService|MockInterface $availabilityService;
    private OrderCreateRequestValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->availabilityService = Mockery::mock(AvailableProductQuantitiesFetchService::class);

        $this->service = new OrderCreateRequestValidationService(
            $this->productRepository,
            $this->promoCodeRepository,
            $this->eventRepository,
            $this->availabilityService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testZeroQuantityTiersAreSkippedDuringValidation(): void
    {
        $eventId = 1;
        $productId = 10;
        $selectedPriceId = 101;
        $unselectedPriceId = 102;

        $this->setupMocks(
            eventId: $eventId,
            productId: $productId,
            priceIds: [$selectedPriceId, $unselectedPriceId],
            priceLabels: ['Selected Tier', 'Unselected Tier'],
            availabilities: [
                ['price_id' => $selectedPriceId, 'quantity_available' => 5, 'quantity_reserved' => 0],
                ['price_id' => $unselectedPriceId, 'quantity_available' => 0, 'quantity_reserved' => 0],
            ],
        );

        $data = [
            'products' => [
                [
                    'product_id' => $productId,
                    'quantities' => [
                        ['price_id' => $selectedPriceId, 'quantity' => 1],
                        ['price_id' => $unselectedPriceId, 'quantity' => 0],
                    ],
                ],
            ],
        ];

        $this->service->validateRequestData($eventId, $data);
        $this->assertTrue(true);
    }

    public function testZeroQuantityTierWithNegativeAvailabilityDoesNotThrow(): void
    {
        $eventId = 1;
        $productId = 10;
        $healthyPriceId = 101;
        $brokenPriceId = 102;

        $this->setupMocks(
            eventId: $eventId,
            productId: $productId,
            priceIds: [$healthyPriceId, $brokenPriceId],
            priceLabels: ['Healthy Tier', 'Broken Tier'],
            availabilities: [
                ['price_id' => $healthyPriceId, 'quantity_available' => 10, 'quantity_reserved' => 0],
                ['price_id' => $brokenPriceId, 'quantity_available' => -5, 'quantity_reserved' => 0],
            ],
        );

        $data = [
            'products' => [
                [
                    'product_id' => $productId,
                    'quantities' => [
                        ['price_id' => $healthyPriceId, 'quantity' => 1],
                        ['price_id' => $brokenPriceId, 'quantity' => 0],
                    ],
                ],
            ],
        ];

        $this->service->validateRequestData($eventId, $data);
        $this->assertTrue(true);
    }

    public function testNonZeroQuantityStillValidatesAgainstAvailability(): void
    {
        $eventId = 1;
        $productId = 10;
        $priceId = 101;

        $this->setupMocks(
            eventId: $eventId,
            productId: $productId,
            priceIds: [$priceId],
            priceLabels: ['Test Tier'],
            availabilities: [
                ['price_id' => $priceId, 'quantity_available' => 2, 'quantity_reserved' => 0],
            ],
        );

        $data = [
            'products' => [
                [
                    'product_id' => $productId,
                    'quantities' => [
                        ['price_id' => $priceId, 'quantity' => 5],
                    ],
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->service->validateRequestData($eventId, $data);
    }

    private function setupMocks(
        int   $eventId,
        int   $productId,
        array $priceIds,
        array $priceLabels,
        array $availabilities,
    ): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getId')->andReturn($eventId);
        $event->shouldReceive('getStatus')->andReturn(EventStatus::LIVE->name);
        $event->shouldReceive('getCurrency')->andReturn('USD');

        $this->eventRepository->shouldReceive('findById')->with($eventId)->andReturn($event);

        $productPrices = new Collection();
        foreach ($priceIds as $i => $priceId) {
            $price = Mockery::mock(ProductPriceDomainObject::class);
            $price->shouldReceive('getId')->andReturn($priceId);
            $price->shouldReceive('getLabel')->andReturn($priceLabels[$i] ?? null);
            $productPrices->push($price);
        }

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getId')->andReturn($productId);
        $product->shouldReceive('getEventId')->andReturn($eventId);
        $product->shouldReceive('getTitle')->andReturn('Test Product');
        $product->shouldReceive('getMaxPerOrder')->andReturn(100);
        $product->shouldReceive('getMinPerOrder')->andReturn(1);
        $product->shouldReceive('isSoldOut')->andReturn(false);
        $product->shouldReceive('getType')->andReturn(ProductPriceType::TIERED->name);
        $product->shouldReceive('getProductPrices')->andReturn($productPrices);

        $this->productRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->productRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$product]));

        $quantityDTOs = collect();
        foreach ($availabilities as $avail) {
            $quantityDTOs->push(AvailableProductQuantitiesDTO::fromArray([
                'product_id' => $productId,
                'price_id' => $avail['price_id'],
                'product_title' => 'Test Product',
                'price_label' => null,
                'quantity_available' => $avail['quantity_available'],
                'quantity_reserved' => $avail['quantity_reserved'],
                'initial_quantity_available' => 100,
                'capacities' => collect(),
            ]));
        }

        $this->availabilityService->shouldReceive('getAvailableProductQuantities')
            ->with($eventId, Mockery::any())
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: $quantityDTOs,
                capacities: collect(),
            ));
    }
}
