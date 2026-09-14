<?php

namespace Tests\Unit\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Exceptions\NoTicketsAvailableException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\EditAttendeeDTO;
use HiEvents\Services\Application\Handlers\Attendee\EditAttendeeHandler;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EditAttendeeHandlerTest extends TestCase
{
    private const EVENT_ID = 10;

    private const ATTENDEE_ID = 7;

    private const OCCURRENCE_ID = 50;

    private const PRODUCT_ID = 30;

    private const OLD_PRICE_ID = 41;

    private const NEW_PRICE_ID = 42;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private ProductRepositoryInterface|MockInterface $productRepository;

    private ProductQuantityUpdateService|MockInterface $productQuantityService;

    private DomainEventDispatcherService|MockInterface $domainEventDispatcherService;

    private AvailableProductQuantitiesFetchService|MockInterface $availableProductQuantitiesFetchService;

    private EditAttendeeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->productQuantityService = Mockery::mock(ProductQuantityUpdateService::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);
        $this->availableProductQuantitiesFetchService = Mockery::mock(AvailableProductQuantitiesFetchService::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->handler = new EditAttendeeHandler(
            $this->attendeeRepository,
            $this->productRepository,
            $this->productQuantityService,
            $databaseManager,
            $this->domainEventDispatcherService,
            $this->availableProductQuantitiesFetchService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_changing_to_a_tier_with_no_room_on_the_attendee_date_is_rejected(): void
    {
        $this->givenAttendeeAndProduct();
        $this->givenAvailabilityForNewTier(0);
        $this->attendeeRepository->shouldNotReceive('updateByIdWhere');

        $this->expectException(NoTicketsAvailableException::class);

        $this->handler->handle($this->dto());
    }

    public function test_changing_to_a_tier_with_room_on_the_attendee_date_moves_the_sale(): void
    {
        $this->givenAttendeeAndProduct();
        $this->givenAvailabilityForNewTier(1);

        $this->productQuantityService->shouldReceive('decreaseQuantitySold')->once()->with(self::OLD_PRICE_ID, 1, self::OCCURRENCE_ID);
        $this->productQuantityService->shouldReceive('increaseQuantitySold')->once()->with(self::NEW_PRICE_ID, 1, self::OCCURRENCE_ID);

        $updated = Mockery::mock(AttendeeDomainObject::class);
        $updated->shouldReceive('getId')->andReturn(self::ATTENDEE_ID);
        $this->attendeeRepository->shouldReceive('updateByIdWhere')->once()->andReturn($updated);
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();

        $this->assertSame($updated, $this->handler->handle($this->dto()));
    }

    private function dto(): EditAttendeeDTO
    {
        return new EditAttendeeDTO(
            first_name: 'Ada',
            last_name: 'Lovelace',
            email: 'ada@example.test',
            product_id: self::PRODUCT_ID,
            product_price_id: self::NEW_PRICE_ID,
            event_id: self::EVENT_ID,
            attendee_id: self::ATTENDEE_ID,
        );
    }

    private function givenAttendeeAndProduct(): void
    {
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getProductPriceId')->andReturn(self::OLD_PRICE_ID);
        $attendee->shouldReceive('getProductId')->andReturn(self::PRODUCT_ID);
        $attendee->shouldReceive('getEventOccurrenceId')->andReturn(self::OCCURRENCE_ID);
        $this->attendeeRepository->shouldReceive('findFirstWhere')->once()->andReturn($attendee);

        $prices = collect([self::OLD_PRICE_ID, self::NEW_PRICE_ID])->map(function (int $id) {
            $price = Mockery::mock(ProductPriceDomainObject::class);
            $price->shouldReceive('getId')->andReturn($id);

            return $price;
        });

        $product = Mockery::mock(ProductDomainObject::class);
        $product->shouldReceive('getEventId')->andReturn(self::EVENT_ID);
        $product->shouldReceive('getType')->andReturn(ProductPriceType::TIERED->name);
        $product->shouldReceive('getProductPrices')->andReturn($prices);
        $this->productRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->productRepository->shouldReceive('findFirstWhere')->once()->andReturn($product);
    }

    private function givenAvailabilityForNewTier(int $quantityAvailable): void
    {
        $this->availableProductQuantitiesFetchService
            ->shouldReceive('getAvailableProductQuantities')
            ->once()
            ->withArgs(fn (int $eventId, bool $ignoreCache, ?int $occurrenceId, bool $applyOccurrenceLimits): bool => $eventId === self::EVENT_ID
                && $occurrenceId === self::OCCURRENCE_ID
                && ! $applyOccurrenceLimits)
            ->andReturn(new AvailableProductQuantitiesResponseDTO(
                productQuantities: collect([
                    AvailableProductQuantitiesDTO::fromArray([
                        'product_id' => self::PRODUCT_ID,
                        'price_id' => self::NEW_PRICE_ID,
                        'product_title' => 'Ticket',
                        'price_label' => 'VIP',
                        'quantity_available' => $quantityAvailable,
                        'quantity_reserved' => 0,
                        'initial_quantity_available' => 1,
                        'product_type' => ProductType::TICKET->name,
                        'quantity_applies_to' => 'OCCURRENCE',
                    ]),
                ]),
            ));
    }
}
