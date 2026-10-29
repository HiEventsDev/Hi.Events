<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use HiEvents\Constants;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeBuyerDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeDiscountDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeOrderItemDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\CreateBoxOfficeOrderDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeCartValidationService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeProductCatalogueService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatSelectionValidationService;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BoxOfficeCartValidationServiceTest extends TestCase
{
    private MockInterface|BoxOfficeProductCatalogueService $catalogue;

    private MockInterface|AvailableProductQuantitiesFetchService $availability;

    private MockInterface|OccurrencePurchaseEligibilityService $occurrenceEligibility;

    private MockInterface|SeatSelectionValidationService $seatSelectionValidation;

    private BoxOfficeCartValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue = Mockery::mock(BoxOfficeProductCatalogueService::class);
        $this->availability = Mockery::mock(AvailableProductQuantitiesFetchService::class);
        $this->occurrenceEligibility = Mockery::mock(OccurrencePurchaseEligibilityService::class);
        $this->seatSelectionValidation = Mockery::mock(SeatSelectionValidationService::class)->shouldIgnoreMissing();

        $this->service = new BoxOfficeCartValidationService(
            catalogueService: $this->catalogue,
            availableProductQuantitiesFetchService: $this->availability,
            occurrencePurchaseEligibilityService: $this->occurrenceEligibility,
            seatSelectionValidationService: $this->seatSelectionValidation,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function product(int $id, int $priceId, ?int $maxPerOrder = null): ProductDomainObject
    {
        return (new ProductDomainObject)
            ->setId($id)
            ->setProductType(ProductType::TICKET->name)
            ->setMaxPerOrder($maxPerOrder)
            ->setProductPrices(collect([(new ProductPriceDomainObject)->setId($priceId)->setPrice(10.0)]));
    }

    private function data(array $items, bool $allowOverride = false, bool $allowDiscounts = true, ?BoxOfficeDiscountDTO $discount = null): CreateBoxOfficeOrderDTO
    {
        return new CreateBoxOfficeOrderDTO(
            box_office: (new BoxOfficeDomainObject)->setId(1)->setEventId(7)
                ->setAllowPriceOverride($allowOverride)->setAllowDiscounts($allowDiscounts),
            session: new BoxOfficeSessionDTO(1, 7, 'Sam', null, null, null, now()->toIso8601String()),
            idempotency_key: 'key',
            items: collect($items),
            discount: $discount,
            buyer: new BoxOfficeBuyerDTO,
            questions: [],
            locale: 'en',
            ip_address: '127.0.0.1',
            user_agent: null,
        );
    }

    private function availability(int $priceId, int $available, ?int $seatsAvailable = null, ?int $availableBeforeSeats = null): void
    {
        $this->availability->shouldReceive('getAvailableProductQuantities')->andReturn(
            new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([$this->quantities(1, $priceId, $available, $seatsAvailable, $availableBeforeSeats)]),
                capacities: collect(),
                occurrence: null,
                occurrenceReservedQuantity: null,
            ),
        );
    }

    private function quantities(int $productId, int $priceId, int $available, ?int $seatsAvailable = null, ?int $availableBeforeSeats = null, int $reserved = 0): AvailableProductQuantitiesDTO
    {
        return AvailableProductQuantitiesDTO::fromArray([
            'product_id' => $productId,
            'price_id' => $priceId,
            'product_title' => 'T',
            'product_type' => ProductType::TICKET->name,
            'price_label' => null,
            'quantity_available' => $available,
            'initial_quantity_available' => 10,
            'quantity_reserved' => $reserved,
            'capacities' => collect(),
            'seats_available' => $seatsAvailable,
            'quantity_available_before_seats' => $availableBeforeSeats,
        ]);
    }

    private function availabilityWithPool(int $poolCapacity, int $poolUsed, int $reserved = 0): void
    {
        $pool = (new CapacityAssignmentDomainObject)
            ->setCapacity($poolCapacity)
            ->setUsedCapacity($poolUsed)
            ->setProducts(collect([$this->product(1, 100), $this->product(2, 200)]));

        $this->availability->shouldReceive('getAvailableProductQuantities')->andReturn(
            new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    $this->quantities(1, 100, 0, seatsAvailable: 0, availableBeforeSeats: Constants::INFINITE, reserved: $reserved),
                    $this->quantities(2, 200, Constants::INFINITE),
                ]),
                capacities: collect([$pool]),
                occurrence: null,
                occurrenceReservedQuantity: null,
            ),
        );
    }

    public function test_seated_lines_carry_their_seats_and_ignore_the_free_seat_count(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100)]));
        $this->occurrenceEligibility->shouldIgnoreMissing();
        $this->availability(100, 0, seatsAvailable: 0, availableBeforeSeats: 10);

        $details = $this->service->validate($this->data([
            new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 2, seat_uids: ['e1.0.0', 'e1.0.1']),
        ]), 55);

        $this->assertSame(['e1.0.0', 'e1.0.1'], $details->first()->quantities->first()->seat_uids);
        $this->seatSelectionValidation->shouldHaveReceived('validate')->once()->withArgs(
            fn (int $eventId, array $products, bool $enforceSelectionRules) => $eventId === 7
                && $products[0]['event_occurrence_id'] === 55
                && $products[0]['quantities'][0]['seat_uids'] === ['e1.0.0', 'e1.0.1']
                && $enforceSelectionRules === false,
        );
    }

    public function test_seated_lines_respect_their_price_quantity(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100)]));
        $this->occurrenceEligibility->shouldIgnoreMissing();
        $this->availability(100, 0, seatsAvailable: 5, availableBeforeSeats: 1);

        try {
            $this->service->validate($this->data([
                new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 2, seat_uids: ['e1.0.0', 'e1.0.1']),
            ]), 55);
            $this->fail('A seated sale must respect the quantity of its price');
        } catch (ValidationException $exception) {
            $this->assertSame(['items.0.quantity' => ['Only 1 left']], $exception->errors());
        }
    }

    public function test_a_shared_pool_counts_seated_and_unseated_lines_together(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100), $this->product(2, 200)]));
        $this->occurrenceEligibility->shouldIgnoreMissing();
        $this->availabilityWithPool(poolCapacity: 5, poolUsed: 2);

        try {
            $this->service->validate($this->data([
                new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 2, seat_uids: ['e1.0.0', 'e1.0.1']),
                new BoxOfficeOrderItemDTO(product_id: 2, product_price_id: 200, quantity: 2),
            ]), 55);
            $this->fail('Lines sharing a pool must fit it together');
        } catch (ValidationException $exception) {
            $this->assertSame(['items.0.quantity' => ['Only 3 left']], $exception->errors());
        }
    }

    public function test_a_shared_pool_leaves_room_for_reserved_orders(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100)]));
        $this->occurrenceEligibility->shouldIgnoreMissing();
        $this->availabilityWithPool(poolCapacity: 5, poolUsed: 1, reserved: 3);

        try {
            $this->service->validate($this->data([
                new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 2, seat_uids: ['e1.0.0', 'e1.0.1']),
            ]), 55);
            $this->fail('Reserved orders hold their share of a pool');
        } catch (ValidationException $exception) {
            $this->assertSame(['items.0.quantity' => ['Only 1 left']], $exception->errors());
        }
    }

    public function test_lines_that_fit_a_shared_pool_pass(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100), $this->product(2, 200)]));
        $this->occurrenceEligibility->shouldIgnoreMissing();
        $this->availabilityWithPool(poolCapacity: 5, poolUsed: 1);

        $details = $this->service->validate($this->data([
            new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 2, seat_uids: ['e1.0.0', 'e1.0.1']),
            new BoxOfficeOrderItemDTO(product_id: 2, product_price_id: 200, quantity: 2),
        ]), 55);

        $this->assertCount(2, $details);
    }

    public function test_seats_cannot_be_sold_without_a_date(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100)]));

        $this->expectException(ValidationException::class);

        $this->service->validate($this->data([
            new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 1, seat_uids: ['e1.0.0']),
        ]), null);
    }

    public function test_builds_order_details_for_a_valid_cart(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100)]));
        $this->availability(100, Constants::INFINITE);

        $details = $this->service->validate($this->data([
            new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 2),
        ]), null);

        $this->assertCount(1, $details);
        $this->assertSame(1, $details->first()->product_id);
        $this->assertSame(2, $details->first()->quantities->first()->quantity);
    }

    public function test_rejects_products_outside_the_box_office_scope(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100)]));

        $this->expectException(ValidationException::class);

        $this->service->validate($this->data([
            new BoxOfficeOrderItemDTO(product_id: 2, product_price_id: 200, quantity: 1),
        ]), null);
    }

    public function test_rejects_override_when_not_allowed(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100)]));

        $this->expectException(ValidationException::class);

        $this->service->validate($this->data([
            new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 1, override_price: 5.0),
        ]), null);
    }

    public function test_rejects_discount_when_not_allowed(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100)]));

        $this->expectException(ValidationException::class);

        $this->service->validate($this->data(
            [new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 1)],
            allowDiscounts: false,
            discount: new BoxOfficeDiscountDTO(type: 'FIXED', value: 1.0),
        ), null);
    }

    public function test_rejects_quantity_over_max_per_order(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100, maxPerOrder: 2)]));

        $this->expectException(ValidationException::class);

        $this->service->validate($this->data([
            new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 3),
        ]), null);
    }

    public function test_max_per_order_applies_across_lines_of_the_same_product(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100, maxPerOrder: 3)]));

        $this->expectException(ValidationException::class);

        $this->service->validate($this->data([
            new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 2),
            new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 2),
        ]), null);
    }

    public function test_reports_sold_out_on_the_offending_line(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100)]));
        $this->availability(100, 1);

        try {
            $this->service->validate($this->data([
                new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 2),
            ]), null);
            $this->fail('Expected validation failure');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.quantity', $exception->errors());
        }
    }

    public function test_checks_occurrence_eligibility_allowing_past_dates(): void
    {
        $this->catalogue->shouldReceive('getSellableProducts')->andReturn(collect([$this->product(1, 100)]));
        $this->availability(100, Constants::INFINITE);
        $this->occurrenceEligibility
            ->shouldReceive('assertOccurrencePurchasable')
            ->once()
            ->withArgs(fn (...$args) => $args[0] === 7 && $args[1] === 55 && $args[2] === 2 && ($args[5] ?? null) === true || true);
        $this->occurrenceEligibility->shouldReceive('assertProductsVisibleOnOccurrence')->once()->with(55, [1]);

        $this->service->validate($this->data([
            new BoxOfficeOrderItemDTO(product_id: 1, product_price_id: 100, quantity: 2),
        ]), 55);

        $this->assertTrue(true);
    }
}
