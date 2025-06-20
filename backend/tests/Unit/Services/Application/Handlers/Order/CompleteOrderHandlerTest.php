<?php

namespace Tests\Unit\Services\Application\Handlers\Order;

use Carbon\Carbon;
use Exception;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\CompleteOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderProductDataDTO;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;

class CompleteOrderHandlerTest extends TestCase
{
    private OrderRepositoryInterface|MockInterface $orderRepository;
    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;
    private QuestionAnswerRepositoryInterface|MockInterface $questionAnswersRepository;
    private ProductQuantityUpdateService|MockInterface $productQuantityUpdateService;
    private ProductPriceRepositoryInterface|MockInterface $productPriceRepository;
    private CompleteOrderHandler $completeOrderHandler;
    private DomainEventDispatcherService $domainEventDispatcherService;
    private AffiliateRepositoryInterface|MockInterface $affiliateRepository;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        Bus::fake();
        DB::shouldReceive('transaction')->andReturnUsing(fn($callback) => $callback(Mockery::mock(Connection::class)));

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->questionAnswersRepository = Mockery::mock(QuestionAnswerRepositoryInterface::class);
        $this->productQuantityUpdateService = Mockery::mock(ProductQuantityUpdateService::class);
        $this->productPriceRepository = Mockery::mock(ProductPriceRepositoryInterface::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);
        $this->affiliateRepository = Mockery::mock(AffiliateRepositoryInterface::class);

        $this->completeOrderHandler = new CompleteOrderHandler(
            $this->orderRepository,
            $this->affiliateRepository,
            $this->attendeeRepository,
            $this->questionAnswersRepository,
            $this->productQuantityUpdateService,
            $this->productPriceRepository,
            $this->domainEventDispatcherService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testHandleSuccessfullyCompletesOrder(): void
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

        $this->completeOrderHandler->handle($orderShortId, $orderData);

        $this->assertTrue(true);
    }

    public function testHandleThrowsResourceNotFoundExceptionWhenOrderNotFound(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $orderShortId = 'NONEXISTENT';
        $orderData = $this->createMockCompleteOrderDTO();

        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturnNull();
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    public function testHandleThrowsResourceConflictExceptionWhenOrderAlreadyProcessed(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('This order has already been processed');

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();

        $order = $this->createMockOrder(OrderStatus::COMPLETED);
        $order->setEmail('d@d.com');
        $order->setTotalGross(0);

        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    public function testHandleThrowsResourceConflictExceptionWhenOrderExpired(): void
    {
        $this->expectException(ResourceConflictException::class);

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $order->setEmail('d@d.com');
        $order->setReservedUntil(Carbon::now()->subHour()->toDateTimeString());
        $order->setTotalGross(100);

        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    public function testHandleUpdatesProductQuantitiesForFreeOrder(): void
    {
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

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->withArgs(function (OrderEvent $event) use ($order) {
                return $event->type === DomainEventType::ORDER_CREATED
                    && $event->orderId === $order->getId();
            })
            ->once();

        $order = $this->completeOrderHandler->handle($orderShortId, $orderData);

        $this->assertSame($order->getStatus(), OrderStatus::COMPLETED->name);
    }

    public function testHandleDoesNotUpdateProductQuantitiesForPaidOrder(): void
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

        $this->completeOrderHandler->handle($orderShortId, $orderData);

        $this->expectNotToPerformAssertions();
    }

    public function testHandleThrowsExceptionWhenAttendeeInsertFails(): void
    {
        $this->expectException(Exception::class);

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $updatedOrder = $this->createMockOrder();

        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($updatedOrder);

        $this->productPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockProductPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(false);

        $this->completeOrderHandler->handle($orderShortId, $orderData);
    }

    public function testExceptionIsThrowWhenAttendeeCountDoesNotMatchOrderItemsCount(): void
    {
        $this->expectException(ResourceConflictException::class);
        $this->expectExceptionMessage('The number of attendees does not match the number of tickets in the order');

        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $updatedOrder = $this->createMockOrder();

        $order->getOrderItems()->first()->setQuantity(2);

        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($updatedOrder);

        $this->productPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockProductPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(true);
        $this->attendeeRepository->shouldReceive('findWhere')->andReturn(new Collection());

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
            products: new Collection([$attendeeDTO])
        );
    }

    private function createMockOrder(OrderStatus $status = OrderStatus::RESERVED): OrderDomainObject|MockInterface
    {
        return (new OrderDomainObject())
            ->setEmail(null)
            ->setReservedUntil(Carbon::now()->addHour()->toDateTimeString())
            ->setStatus($status->name)
            ->setId(1)
            ->setEventId(1)
            ->setLocale('en')
            ->setTotalGross(10)
            ->setOrderItems(new Collection([
                $this->createMockOrderItem()
            ]));
    }

    private function createMockOrderItem(): OrderItemDomainObject|MockInterface
    {
        return (new OrderItemDomainObject())
            ->setId(1)
            ->setProductId(1)
            ->setQuantity(1)
            ->setPrice(10)
            ->setTotalGross(10)
            ->setProductPriceId(1);
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
}
