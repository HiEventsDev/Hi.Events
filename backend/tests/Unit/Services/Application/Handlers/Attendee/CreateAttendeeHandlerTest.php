<?php

namespace Tests\Unit\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\InvalidProductPriceId;
use HiEvents\Exceptions\NoTicketsAvailableException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\CreateAttendeeHandler;
use HiEvents\Services\Application\Handlers\Attendee\DTO\CreateAttendeeDTO;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use HiEvents\Services\Domain\Tax\TaxAndFeeRollupService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateAttendeeHandlerTest extends TestCase
{
    private const EVENT_ID = 10;

    private const ORDER_ID = 20;

    private const ATTENDEE_ID = 30;

    private const PRODUCT_ID = 40;

    private const PRODUCT_PRICE_ID = 41;

    private const OCCURRENCE_ID = 50;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private ProductRepositoryInterface|MockInterface $productRepository;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private ProductQuantityUpdateService|MockInterface $productQuantityService;

    private OrderManagementService|MockInterface $orderManagementService;

    private DomainEventDispatcherService|MockInterface $domainEventDispatcherService;

    private OccurrencePurchaseEligibilityService|MockInterface $occurrenceEligibilityService;

    private OrderAuditLogService|MockInterface $orderAuditLogService;

    private CreateAttendeeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->productQuantityService = Mockery::mock(ProductQuantityUpdateService::class);
        $this->orderManagementService = Mockery::mock(OrderManagementService::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);
        $this->occurrenceEligibilityService = Mockery::mock(OccurrencePurchaseEligibilityService::class);
        $this->orderAuditLogService = Mockery::mock(OrderAuditLogService::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->productRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->handler = new CreateAttendeeHandler(
            $this->attendeeRepository,
            $this->orderRepository,
            $this->productRepository,
            $this->eventRepository,
            $this->occurrenceRepository,
            $this->productQuantityService,
            $databaseManager,
            Mockery::mock(TaxAndFeeRepositoryInterface::class),
            new TaxAndFeeRollupService,
            $this->orderManagementService,
            $this->domainEventDispatcherService,
            $this->occurrenceEligibilityService,
            $this->orderAuditLogService,
        );
    }

    public function test_a_manual_attendee_creates_a_completed_order_and_fires_the_completion_event(): void
    {
        $this->givenSingleEvent();
        $this->givenOccurrenceIsPurchasable();
        $this->givenTicketProduct();
        $this->productRepository->shouldReceive('getQuantityRemainingForProductPrice')
            ->once()->with(self::PRODUCT_ID, self::PRODUCT_PRICE_ID)->andReturn(5);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $attributes): bool => $attributes[OrderDomainObjectAbstract::STATUS] === OrderStatus::COMPLETED->name
                && $attributes[OrderDomainObjectAbstract::PAYMENT_STATUS] === OrderPaymentStatus::PAYMENT_RECEIVED->name
                && $attributes[OrderDomainObjectAbstract::TOTAL_GROSS] === 25.0
                && $attributes[OrderDomainObjectAbstract::EVENT_ID] === self::EVENT_ID
                && $attributes[OrderDomainObjectAbstract::IS_MANUALLY_CREATED] === true
                && $attributes[OrderDomainObjectAbstract::CURRENCY] === 'USD')
            ->andReturn($this->order());

        $this->orderRepository->shouldReceive('addOrderItem')
            ->once()
            ->withArgs(fn (array $attributes): bool => $attributes[OrderItemDomainObjectAbstract::QUANTITY] === 1
                && $attributes[OrderItemDomainObjectAbstract::PRODUCT_PRICE_ID] === self::PRODUCT_PRICE_ID
                && $attributes[OrderItemDomainObjectAbstract::PRODUCT_TYPE] === ProductType::TICKET->name
                && $attributes[OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID] === self::OCCURRENCE_ID
                && $attributes[OrderItemDomainObjectAbstract::TOTAL_GROSS] === 25.0)
            ->andReturn(new OrderItemDomainObject);

        $this->attendeeRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $attributes): bool => $attributes[AttendeeDomainObjectAbstract::STATUS] === AttendeeStatus::ACTIVE->name
                && $attributes[AttendeeDomainObjectAbstract::ORDER_ID] === self::ORDER_ID
                && $attributes[AttendeeDomainObjectAbstract::PRODUCT_PRICE_ID] === self::PRODUCT_PRICE_ID
                && $attributes[AttendeeDomainObjectAbstract::EVENT_OCCURRENCE_ID] === self::OCCURRENCE_ID)
            ->andReturn((new AttendeeDomainObject)->setId(self::ATTENDEE_ID));

        $this->orderManagementService->shouldReceive('updateOrderTotals')->once()->andReturn($this->order());

        $this->productQuantityService->shouldReceive('increaseQuantitySold')
            ->once()
            ->with(self::PRODUCT_PRICE_ID, 1, self::OCCURRENCE_ID);

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn (OrderEvent $event) => $event->type === DomainEventType::ORDER_CREATED
                && $event->orderId === self::ORDER_ID));

        $this->orderAuditLogService->shouldNotReceive('logManualAttendeeCapacityOverride');

        $attendee = $this->handler->handle($this->dto(amountPaid: 25, occurrenceId: self::OCCURRENCE_ID));

        $this->assertSame(self::ATTENDEE_ID, $attendee->getId());

        Event::assertDispatched(
            OrderStatusChangedEvent::class,
            fn (OrderStatusChangedEvent $event) => $event->order->getId() === self::ORDER_ID
                && $event->order->isOrderCompleted()
                && $event->sendEmails === true
        );
    }

    public function test_a_single_event_resolves_its_only_occurrence_when_none_is_given(): void
    {
        $this->givenSingleEvent();
        $this->occurrenceRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['event_id' => self::EVENT_ID])
            ->andReturn((new EventOccurrenceDomainObject)->setId(self::OCCURRENCE_ID));
        $this->givenOccurrenceIsPurchasable();
        $this->givenTicketProduct();
        $this->givenHappyPathPersistence();

        $this->productQuantityService->shouldReceive('increaseQuantitySold')
            ->once()
            ->with(self::PRODUCT_PRICE_ID, 1, self::OCCURRENCE_ID);

        $this->handler->handle($this->dto(amountPaid: 0, occurrenceId: null));

        Event::assertDispatched(OrderStatusChangedEvent::class);
    }

    public function test_a_recurring_event_requires_an_occurrence(): void
    {
        $this->eventRepository->shouldReceive('findById')
            ->once()
            ->with(self::EVENT_ID)
            ->andReturn((new EventDomainObject)->setId(self::EVENT_ID)->setType(EventType::RECURRING->name)->setCurrency('USD'));

        $this->occurrenceRepository->shouldNotReceive('findFirstWhere');
        $this->orderRepository->shouldNotReceive('create');

        $this->expectException(ValidationException::class);

        $this->handler->handle($this->dto(amountPaid: 0, occurrenceId: null));
    }

    public function test_a_free_manual_attendee_requires_no_payment(): void
    {
        $this->givenSingleEvent();
        $this->givenOccurrenceIsPurchasable();
        $this->givenTicketProduct();
        $this->givenHappyPathPersistence(expectedPaymentStatus: OrderPaymentStatus::NO_PAYMENT_REQUIRED);
        $this->productQuantityService->shouldReceive('increaseQuantitySold')->once();

        $this->handler->handle($this->dto(amountPaid: 0, occurrenceId: self::OCCURRENCE_ID));
    }

    public function test_no_attendee_is_created_when_the_product_is_sold_out(): void
    {
        $this->givenSingleEvent();
        $this->givenOccurrenceIsPurchasable();
        $this->givenTicketProduct();
        $this->orderRepository->shouldReceive('create')->once()->andReturn($this->order());
        $this->productRepository->shouldReceive('getQuantityRemainingForProductPrice')->once()->andReturn(0);

        $this->attendeeRepository->shouldNotReceive('create');
        $this->productQuantityService->shouldNotReceive('increaseQuantitySold');

        $this->expectException(NoTicketsAvailableException::class);

        try {
            $this->handler->handle($this->dto(amountPaid: 10, occurrenceId: self::OCCURRENCE_ID));
        } finally {
            Event::assertNotDispatched(OrderStatusChangedEvent::class);
        }
    }

    public function test_a_price_that_does_not_belong_to_the_product_is_rejected(): void
    {
        $this->givenSingleEvent();
        $this->givenOccurrenceIsPurchasable();
        $this->givenTicketProduct();
        $this->orderRepository->shouldReceive('create')->once()->andReturn($this->order());

        $this->attendeeRepository->shouldNotReceive('create');
        $this->productQuantityService->shouldNotReceive('increaseQuantitySold');

        $this->expectException(InvalidProductPriceId::class);

        $this->handler->handle($this->dto(amountPaid: 10, occurrenceId: self::OCCURRENCE_ID, productPriceId: 999));
    }

    public function test_a_capacity_override_is_audit_logged(): void
    {
        $this->givenSingleEvent();
        $this->occurrenceEligibilityService->shouldReceive('assertOccurrencePurchasable')
            ->once()
            ->withArgs(fn (int $eventId, int $occurrenceId, int $additionalQuantity, bool $overrideCapacity): bool => $overrideCapacity === true);
        $this->occurrenceEligibilityService->shouldReceive('assertProductsVisibleOnOccurrence')->once();
        $this->givenTicketProduct();
        $this->givenHappyPathPersistence();
        $this->productQuantityService->shouldReceive('increaseQuantitySold')->once();

        $this->orderAuditLogService->shouldReceive('logManualAttendeeCapacityOverride')
            ->once()
            ->with(self::EVENT_ID, self::ORDER_ID, self::ATTENDEE_ID, self::OCCURRENCE_ID, '10.0.0.1', 'agent');

        $this->handler->handle($this->dto(amountPaid: 0, occurrenceId: self::OCCURRENCE_ID, overrideCapacity: true));
    }

    private function dto(
        float $amountPaid,
        ?int $occurrenceId,
        ?int $productPriceId = self::PRODUCT_PRICE_ID,
        bool $overrideCapacity = false,
    ): CreateAttendeeDTO {
        return new CreateAttendeeDTO(
            first_name: 'Manual',
            last_name: 'Attendee',
            email: 'manual@example.com',
            product_id: self::PRODUCT_ID,
            event_id: self::EVENT_ID,
            send_confirmation_email: true,
            amount_paid: $amountPaid,
            locale: 'en',
            product_price_id: $productPriceId,
            event_occurrence_id: $occurrenceId,
            override_capacity: $overrideCapacity,
            client_ip: '10.0.0.1',
            client_user_agent: 'agent',
        );
    }

    private function order(): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setEventId(self::EVENT_ID)
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setCurrency('USD');
    }

    private function givenSingleEvent(): void
    {
        $this->eventRepository->shouldReceive('findById')
            ->with(self::EVENT_ID)
            ->andReturn((new EventDomainObject)->setId(self::EVENT_ID)->setType(EventType::SINGLE->name)->setCurrency('USD'));
    }

    private function givenOccurrenceIsPurchasable(): void
    {
        $this->occurrenceEligibilityService->shouldReceive('assertOccurrencePurchasable')
            ->once()
            ->withArgs(fn (int $eventId, int $occurrenceId, int $additionalQuantity, bool $overrideCapacity): bool => $eventId === self::EVENT_ID
                && $occurrenceId === self::OCCURRENCE_ID
                && $additionalQuantity === 1
                && $overrideCapacity === false);
        $this->occurrenceEligibilityService->shouldReceive('assertProductsVisibleOnOccurrence')
            ->once()
            ->with(self::OCCURRENCE_ID, [self::PRODUCT_ID]);
    }

    private function givenTicketProduct(): void
    {
        $product = (new ProductDomainObject)
            ->setId(self::PRODUCT_ID)
            ->setTitle('General Admission')
            ->setProductType(ProductType::TICKET->name)
            ->setProductPrices(collect([(new ProductPriceDomainObject)->setId(self::PRODUCT_PRICE_ID)]));

        $this->productRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                ProductDomainObjectAbstract::ID => self::PRODUCT_ID,
                ProductDomainObjectAbstract::EVENT_ID => self::EVENT_ID,
                ProductDomainObjectAbstract::PRODUCT_TYPE => ProductType::TICKET->name,
            ])
            ->andReturn($product);
    }

    private function givenHappyPathPersistence(
        OrderPaymentStatus $expectedPaymentStatus = OrderPaymentStatus::NO_PAYMENT_REQUIRED,
    ): void {
        $this->productRepository->shouldReceive('getQuantityRemainingForProductPrice')->once()->andReturn(5);
        $this->orderRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $attributes): bool => $attributes[OrderDomainObjectAbstract::PAYMENT_STATUS] === $expectedPaymentStatus->name)
            ->andReturn($this->order());
        $this->orderRepository->shouldReceive('addOrderItem')->once()->andReturn(new OrderItemDomainObject);
        $this->attendeeRepository->shouldReceive('create')->once()->andReturn((new AttendeeDomainObject)->setId(self::ATTENDEE_ID));
        $this->orderManagementService->shouldReceive('updateOrderTotals')->once()->andReturn($this->order());
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();
    }
}
