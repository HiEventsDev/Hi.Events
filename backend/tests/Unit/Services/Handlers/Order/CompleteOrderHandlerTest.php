<?php

namespace Tests\Unit\Services\Handlers\Order;

use Carbon\Carbon;
use Exception;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\TicketPriceDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketPriceRepositoryInterface;
use HiEvents\Services\Domain\Ticket\TicketQuantityUpdateService;
use HiEvents\Services\Handlers\Order\CompleteOrderHandler;
use HiEvents\Services\Handlers\Order\DTO\CompleteOrderAttendeeDTO;
use HiEvents\Services\Handlers\Order\DTO\CompleteOrderDTO;
use HiEvents\Services\Handlers\Order\DTO\CompleteOrderOrderDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Tests\TestCase;
use Illuminate\Database\Connection;

class CompleteOrderHandlerTest extends TestCase
{
    private OrderRepositoryInterface|MockInterface $orderRepository;
    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;
    private QuestionAnswerRepositoryInterface|MockInterface $questionAnswersRepository;
    private TicketQuantityUpdateService|MockInterface $ticketQuantityUpdateService;
    private TicketPriceRepositoryInterface|MockInterface $ticketPriceRepository;
    private CompleteOrderHandler $completeOrderHandler;

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
        $this->ticketQuantityUpdateService = Mockery::mock(TicketQuantityUpdateService::class);
        $this->ticketPriceRepository = Mockery::mock(TicketPriceRepositoryInterface::class);

        $this->completeOrderHandler = new CompleteOrderHandler(
            $this->orderRepository,
            $this->attendeeRepository,
            $this->questionAnswersRepository,
            $this->ticketQuantityUpdateService,
            $this->ticketPriceRepository
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

        $this->ticketPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockTicketPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(true);
        $this->attendeeRepository->shouldReceive('findWhere')->andReturn(new Collection([$this->createMockAttendee()]));

        $this->ticketQuantityUpdateService->shouldReceive('updateQuantitiesFromOrder');

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

    public function testHandleUpdatesTicketQuantitiesForFreeOrder(): void
    {
        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $updatedOrder = $this->createMockOrder(OrderStatus::COMPLETED);

        $order->setTotalGross(0);
        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($updatedOrder);

        $this->ticketPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockTicketPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(true);
        $this->attendeeRepository->shouldReceive('findWhere')->andReturn(new Collection([$this->createMockAttendee()]));

        $this->ticketQuantityUpdateService->shouldReceive('updateQuantitiesFromOrder')->once();

        $order = $this->completeOrderHandler->handle($orderShortId, $orderData);

        $this->assertSame($order->getStatus(), OrderStatus::COMPLETED->name);
    }

    public function testHandleDoesNotUpdateTicketQuantitiesForPaidOrder(): void
    {
        $orderShortId = 'ABC123';
        $orderData = $this->createMockCompleteOrderDTO();
        $order = $this->createMockOrder();
        $updatedOrder = $this->createMockOrder();

        $order->setTotalGross(10);

        $this->orderRepository->shouldReceive('findByShortId')->with($orderShortId)->andReturn($order);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('updateFromArray')->andReturn($updatedOrder);

        $this->ticketPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockTicketPrice()]));

        $this->attendeeRepository->shouldReceive('insert')->andReturn(true);
        $this->attendeeRepository->shouldReceive('findWhere')->andReturn(new Collection([$this->createMockAttendee()]));

        $this->ticketQuantityUpdateService->shouldNotReceive('updateQuantitiesFromOrder');

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

        $this->ticketPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockTicketPrice()]));

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

        $this->ticketPriceRepository->shouldReceive('findWhereIn')->andReturn(new Collection([$this->createMockTicketPrice()]));

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

        $attendeeDTO = new CompleteOrderAttendeeDTO(
            first_name: 'John',
            last_name: 'Doe',
            email: 'john@example.com',
            ticket_price_id: 1
        );

        return new CompleteOrderDTO(
            order: $orderDTO,
            attendees: new Collection([$attendeeDTO])
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
            ->setTicketId(1)
            ->setQuantity(1)
            ->setPrice(10)
            ->setTotalGross(10)
            ->setTicketPriceId(1);
    }

    private function createMockTicketPrice(): TicketPriceDomainObject|MockInterface
    {
        $ticketPrice = Mockery::mock(TicketPriceDomainObject::class);
        $ticketPrice->shouldReceive('getId')->andReturn(1);
        $ticketPrice->shouldReceive('getTicketId')->andReturn(1);
        return $ticketPrice;
    }

    private function createMockAttendee(): AttendeeDomainObject|MockInterface
    {
        $attendee = Mockery::mock(AttendeeDomainObject::class);
        $attendee->shouldReceive('getId')->andReturn(1);
        $attendee->shouldReceive('getTicketId')->andReturn(1);
        return $attendee;
    }
}
