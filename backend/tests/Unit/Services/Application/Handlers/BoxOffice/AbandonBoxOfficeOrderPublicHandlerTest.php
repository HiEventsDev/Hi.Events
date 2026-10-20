<?php

namespace Tests\Unit\Services\Application\Handlers\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\AbandonBoxOfficeOrderPublicHandler;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderLookupService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalPaymentService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AbandonBoxOfficeOrderPublicHandlerTest extends TestCase
{
    private MockInterface|BoxOfficeOrderLookupService $lookup;

    private MockInterface|StripeTerminalPaymentService $terminalPaymentService;

    private MockInterface|OrderRepositoryInterface $orderRepository;

    private MockInterface|AttendeeRepositoryInterface $attendeeRepository;

    private MockInterface|TransactionLockService $transactionLockService;

    private AbandonBoxOfficeOrderPublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lookup = Mockery::mock(BoxOfficeOrderLookupService::class);
        $this->terminalPaymentService = Mockery::mock(StripeTerminalPaymentService::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);

        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('findById')->andReturn((new EventDomainObject)->setId(7));

        $this->transactionLockService = Mockery::mock(TransactionLockService::class);
        $this->transactionLockService->shouldReceive('lockOrder')->with('o_x')->byDefault();

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->handler = new AbandonBoxOfficeOrderPublicHandler(
            orderLookupService: $this->lookup,
            eventRepository: $eventRepository,
            terminalPaymentService: $this->terminalPaymentService,
            orderRepository: $this->orderRepository,
            attendeeRepository: $this->attendeeRepository,
            databaseManager: $databaseManager,
            transactionLockService: $this->transactionLockService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_abandoning_releases_the_card_hold_and_cancels_the_placeholder_attendees(): void
    {
        $boxOffice = (new BoxOfficeDomainObject)->setId(1)->setEventId(7);
        $reserved = (new OrderDomainObject)->setId(41)->setEventId(7)->setStatus(OrderStatus::RESERVED->name);
        $abandoned = (new OrderDomainObject)->setId(41)->setStatus(OrderStatus::ABANDONED->name);
        $this->lookup->shouldReceive('findOrFail')->with($boxOffice, 'o_x')->andReturn($reserved, $reserved, $abandoned);
        $this->terminalPaymentService->shouldReceive('releaseCardPayment')->once()->withArgs(fn ($order, $event, $readerId) => $order === $reserved && $readerId === 9);
        $this->orderRepository->shouldReceive('updateFromArray')->once()->with(41, [OrderDomainObjectAbstract::STATUS => OrderStatus::ABANDONED->name]);
        $this->attendeeRepository->shouldReceive('updateWhere')->once()->with(['status' => AttendeeStatus::CANCELLED->name], ['order_id' => 41]);

        $result = $this->handler->handle($boxOffice, 'o_x', 9);

        $this->assertSame($abandoned, $result);
    }

    public function test_a_completed_sale_cannot_be_abandoned(): void
    {
        $boxOffice = (new BoxOfficeDomainObject)->setId(1)->setEventId(7);
        $this->lookup->shouldReceive('findOrFail')->andReturn((new OrderDomainObject)->setId(41)->setStatus(OrderStatus::COMPLETED->name));
        $this->terminalPaymentService->shouldNotReceive('releaseCardPayment');
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($boxOffice, 'o_x', null);
    }

    public function test_a_sale_completed_while_the_card_hold_was_released_is_not_abandoned(): void
    {
        $boxOffice = (new BoxOfficeDomainObject)->setId(1)->setEventId(7);
        $reserved = (new OrderDomainObject)->setId(41)->setEventId(7)->setStatus(OrderStatus::RESERVED->name);
        $completed = (new OrderDomainObject)->setId(41)->setEventId(7)->setStatus(OrderStatus::COMPLETED->name);
        $this->lookup->shouldReceive('findOrFail')->andReturn($reserved, $completed);
        $this->terminalPaymentService->shouldReceive('releaseCardPayment')->once();
        $this->transactionLockService->shouldReceive('lockOrder')->once()->with('o_x');
        $this->orderRepository->shouldNotReceive('updateFromArray');
        $this->attendeeRepository->shouldNotReceive('updateWhere');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($boxOffice, 'o_x', null);
    }
}
