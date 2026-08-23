<?php

namespace Tests\Unit\Services\Application\Handlers\Order;

use Carbon\Carbon;
use Exception;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\CompleteOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderProductDataDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\OrderQuestionsDTO;
use HiEvents\Services\Domain\Order\OccurrenceStatusValidator;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class CompleteOrderHandlerTest extends TestCase
{
    private array $executedStatements = [];

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private QuestionAnswerRepositoryInterface|MockInterface $questionAnswersRepository;

    private ProductQuantityUpdateService|MockInterface $productQuantityUpdateService;

    private ProductPriceRepositoryInterface|MockInterface $productPriceRepository;

    private CompleteOrderHandler $completeOrderHandler;

    private DomainEventDispatcherService $domainEventDispatcherService;

    private AffiliateRepositoryInterface|MockInterface $affiliateRepository;

    private EventSettingsRepositoryInterface $eventSettingsRepository;

    private CheckoutSessionManagementService|MockInterface $sessionManagementService;

    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        Bus::fake();
        DB::shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback(Mockery::mock(Connection::class)));
        $this->executedStatements = [];
        DB::shouldReceive('statement')->andReturnUsing(function (string $sql, array $bindings = []) {
            $this->executedStatements[] = [$sql, $bindings];

            return true;
        });

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->questionAnswersRepository = Mockery::mock(QuestionAnswerRepositoryInterface::class);
        $this->productQuantityUpdateService = Mockery::mock(ProductQuantityUpdateService::class);
        $this->productPriceRepository = Mockery::mock(ProductPriceRepositoryInterface::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);
        $this->affiliateRepository = Mockery::mock(AffiliateRepositoryInterface::class);
        $this->eventSettingsRepository = Mockery::mock(EventSettingsRepositoryInterface::class);
        $this->sessionManagementService = Mockery::mock(CheckoutSessionManagementService::class);
        $this->sessionManagementService->shouldReceive('verifySession')->andReturn(true)->byDefault();
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->occurrenceRepository->shouldReceive('findWhereIn')->andReturn(
            collect([
                (new EventOccurrenceDomainObject)
                    ->setId(1)
                    ->setStatus(EventOccurrenceStatus::ACTIVE->name)
                    ->setStartDate(Carbon::now()->addDay()->toDateTimeString()),
            ])
        )->byDefault();

        $this->completeOrderHandler = new CompleteOrderHandler(
            $this->orderRepository,
            $this->affiliateRepository,
            $this->attendeeRepository,
            $this->questionAnswersRepository,
            $this->productQuantityUpdateService,
            $this->productPriceRepository,
            $this->domainEventDispatcherService,
            $this->eventSettingsRepository,
            $this->sessionManagementService,
            new OccurrenceStatusValidator($this->occurrenceRepository),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_successfully_completes_order(): void
    {
        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $updatedOrder = $this->createMockOrder();

        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($updatedOrder);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->productPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockProductPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(true);
        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockAttendee()]));

        $this->productQuantityUpdateService->shouldReceive('updateQuantitiesFromOrder');

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());

        $this->completeOrderHandler->handle($orderShortId, $orderData);

        $this->assertTrue(true);
    }

    public function test_handle_stores_unwrapped_order_answers_and_skips_blank_ones(): void
    {
        $orderShortId = 'ABC123';
        $orderData = new CompleteOrderDTO(
            order: new CompleteOrderOrderDTO(
                first_name: 'John',
                last_name: 'Doe',
                email: 'john@example.com',
                questions: new Collection([
                    new OrderQuestionsDTO(question_id: 10, response: ['answer' => null]),
                    new OrderQuestionsDTO(question_id: 11, response: ['answer' => '']),
                    new OrderQuestionsDTO(question_id: 12, response: ['answer' => 'Band 5']),
                ]),
            ),
            products: new Collection([
                new CompleteOrderProductDataDTO(
                    product_price_id: 1,
                    first_name: 'John',
                    last_name: 'Doe',
                    email: 'john@example.com'
                ),
            ]),
            event_id: 1,
        );
        $order = $this->createMockOrder();

        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($this->createMockOrder());
        $this->productPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockProductPrice()]));
        $this->attendeeRepository->shouldReceive('insert')->andReturn(true);
        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockAttendee()]));
        $this->productQuantityUpdateService->shouldReceive('updateQuantitiesFromOrder');
        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());

        $createdAnswers = [];
        $this->questionAnswersRepository->shouldReceive('create')->andReturnUsing(function (array $data) use (&$createdAnswers) {
            $createdAnswers[] = $data;

            return new QuestionAnswerDomainObject;
        });

        $this->completeOrderHandler->handle($orderShortId, $orderData);

        $this->assertSame([
            ['question_id' => 12, 'answer' => 'Band 5', 'order_id' => 1],
        ], $createdAnswers);
    }

    public function test_handle_takes_an_advisory_lock_keyed_on_the_order_before_reading_it(): void
    {
        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $updatedOrder = $this->createMockOrder();

        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($updatedOrder);

        $this->productPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockProductPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(true);
        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockAttendee()]));

        $this->productQuantityUpdateService->shouldReceive('updateQuantitiesFromOrder');

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());

        $this->completeOrderHandler->handle($orderShortId, $orderData);

        $this->assertSame(
            [['SELECT pg_advisory_xact_lock(hashtext(?))', [$orderShortId]]],
            $this->executedStatements,
        );
    }

    public function test_handle_throws_resource_not_found_exception_when_order_not_found(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $orderShortId = 'NONEXISTENT';
        $orderData = $this->createMockCompleteOrderDTO();

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());
        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturnNull();
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    public function test_handle_throws_resource_conflict_exception_when_order_already_processed(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('This order has already been processed');

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();

        $order = $this->createMockOrder(OrderStatus::COMPLETED);
        $order->setEmail('d@d.com');
        $order->setTotalGross(0);

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());
        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    public function test_handle_throws_resource_conflict_exception_when_order_expired(): void
    {
        $this->expectException(ResourceConflictException::class);

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $order->setEmail('d@d.com');
        $order->setReservedUntil(Carbon::now()->subHour()->toDateTimeString());
        $order->setTotalGross(100);

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());
        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    public function test_handle_updates_product_quantities_for_free_order(): void
    {
        Event::fake();

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $updatedOrder = $this->createMockOrder(OrderStatus::COMPLETED);

        $order->setTotalGross(0);
        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($updatedOrder);

        $this->productPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockProductPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(true);
        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockAttendee()]));

        $this->productQuantityUpdateService->shouldReceive('updateQuantitiesFromOrder')->once();

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->withArgs(function (OrderEvent $event) use ($order) {
                return $event->type === DomainEventType::ORDER_CREATED
                    && $event->orderId === $order->getId();
            })
            ->once();

        $order = $this->completeOrderHandler->handle($orderShortId, $orderData);

        $this->assertSame($order->getStatus(), OrderStatus::COMPLETED->name);
    }

    public function test_handle_does_not_update_product_quantities_for_paid_order(): void
    {
        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $updatedOrder = $this->createMockOrder();

        $order->setTotalGross(10);

        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($updatedOrder);

        $this->productPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockProductPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(true);
        $this->attendeeRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockAttendee()]));

        $this->productQuantityUpdateService->shouldNotReceive('updateQuantitiesFromOrder');

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());

        $this->completeOrderHandler->handle($orderShortId, $orderData);

        $this->expectNotToPerformAssertions();
    }

    public function test_handle_throws_exception_when_attendee_insert_fails(): void
    {
        $this->expectException(Exception::class);

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $updatedOrder = $this->createMockOrder();

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());
        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($updatedOrder);

        $this->productPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockProductPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(false);

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    public function test_exception_is_throw_when_attendee_count_does_not_match_order_items_count(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('The number of attendees does not match the number of tickets in the order');

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $updatedOrder = $this->createMockOrder();

        $order->getOrderItems()->first()->setQuantity(2);

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());
        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($updatedOrder);

        $this->productPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockProductPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(true);
        $this->attendeeRepository->shouldReceive('findWhere')->andReturn(new Collection);

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    public function test_handle_throws_resource_conflict_exception_when_occurrence_is_cancelled(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('This event date has been cancelled');

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());
        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->occurrenceRepository->shouldReceive('findWhereIn')->andReturn(
            collect([(new EventOccurrenceDomainObject)->setId(1)->setStatus(EventOccurrenceStatus::CANCELLED->name)])
        );

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    public function test_handle_throws_resource_conflict_exception_when_occurrence_has_ended(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('This event date has already ended');

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();

        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($this->createMockEventSetting());
        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->occurrenceRepository->shouldReceive('findWhereIn')->andReturn(
            collect([
                (new EventOccurrenceDomainObject)
                    ->setId(1)
                    ->setStatus(EventOccurrenceStatus::ACTIVE->name)
                    ->setStartDate(Carbon::now()->subDay()->toDateTimeString()),
            ])
        );

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    private function createMockCompleteOrderDTO(): CompleteOrderDTO
    {
        $orderDTO = new CompleteOrderOrderDTO(
            first_name: 'John',
            last_name: 'Doe',
            email: 'john@example.com',
            questions: null,
        );

        $attendeeDTO = new CompleteOrderProductDataDTO(
            product_price_id: 1,
            first_name: 'John',
            last_name: 'Doe',
            email: 'john@example.com'
        );

        return new CompleteOrderDTO(
            order: $orderDTO,
            products: new Collection([$attendeeDTO]), event_id: 1
        );
    }

    private function createMockOrder(OrderStatus $status = OrderStatus::RESERVED): OrderDomainObject|MockInterface
    {
        return (new OrderDomainObject)
            ->setEmail(null)
            ->setSessionId('test-session-id')
            ->setReservedUntil(Carbon::now()->addHour()->toDateTimeString())
            ->setStatus($status->name)
            ->setId(1)
            ->setEventId(1)
            ->setLocale('en')
            ->setTotalGross(10)
            ->setOrderItems(new Collection([
                $this->createMockOrderItem(),
            ]));
    }

    private function createMockOrderItem(): OrderItemDomainObject|MockInterface
    {
        return (new OrderItemDomainObject)
            ->setId(1)
            ->setProductId(1)
            ->setQuantity(1)
            ->setPrice(10)
            ->setTotalGross(10)
            ->setProductPriceId(1)
            ->setEventOccurrenceId(1);
    }

    private function createMockProductPrice(): ProductPriceDomainObject|MockInterface
    {
        $productPrice = Mockery::mock(ProductPriceDomainObject::class);
        $productPrice->shouldReceive('getId')->andReturn(1);
        $productPrice->shouldReceive('getProductId')->andReturn(1);

        return $productPrice;
    }

    private function createMockAttendee(): AttendeeDomainObject|MockInterface
    {
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getId')->andReturn(1);
        $attendee->shouldReceive('getProductId')->andReturn(1);

        return $attendee;
    }

    private function createMockEventSetting(): EventSettingDomainObject
    {
        return (new EventSettingDomainObject)
            ->setId(1)
            ->setEventId(1);
    }
}
