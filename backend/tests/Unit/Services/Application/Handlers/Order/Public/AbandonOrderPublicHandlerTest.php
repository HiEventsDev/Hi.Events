<?php

namespace Tests\Unit\Services\Application\Handlers\Order\Public;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\Public\AbandonOrderPublicHandler;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Log\Logger;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AbandonOrderPublicHandlerTest extends TestCase
{
    private MockInterface|OrderRepositoryInterface $orderRepository;

    private MockInterface|CheckoutSessionManagementService $sessionService;

    private MockInterface|TransactionLockService $transactionLockService;

    private AbandonOrderPublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->sessionService = Mockery::mock(CheckoutSessionManagementService::class);
        $this->sessionService->shouldReceive('verifySession')->with('sess_1')->andReturnTrue()->byDefault();
        $this->transactionLockService = Mockery::mock(TransactionLockService::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->handler = new AbandonOrderPublicHandler(
            orderRepository: $this->orderRepository,
            sessionService: $this->sessionService,
            transactionLockService: $this->transactionLockService,
            databaseManager: $databaseManager,
            logger: Mockery::mock(Logger::class)->shouldIgnoreMissing(),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_a_reserved_order_is_abandoned_under_the_order_lock(): void
    {
        $abandoned = $this->order(OrderStatus::ABANDONED);
        $this->orderRepository->shouldReceive('findByShortId')->with('o_x')->andReturn($this->order(OrderStatus::RESERVED));
        $this->transactionLockService->shouldReceive('lockOrder')->once()->with('o_x')->ordered();
        $this->orderRepository->shouldReceive('updateFromArray')->once()->with(41, [OrderDomainObjectAbstract::STATUS => OrderStatus::ABANDONED->name])->ordered();
        $this->orderRepository->shouldReceive('findById')->with(41)->andReturn($abandoned);

        $this->assertSame($abandoned, $this->handler->handle('o_x'));
    }

    public function test_an_order_completed_while_waiting_for_the_lock_is_not_abandoned(): void
    {
        $this->orderRepository->shouldReceive('findByShortId')->with('o_x')->andReturn(
            $this->order(OrderStatus::RESERVED),
            $this->order(OrderStatus::COMPLETED),
        );
        $this->transactionLockService->shouldReceive('lockOrder')->once()->with('o_x');
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle('o_x');
    }

    public function test_another_session_cannot_abandon_the_order(): void
    {
        $this->orderRepository->shouldReceive('findByShortId')->with('o_x')->andReturn($this->order(OrderStatus::RESERVED));
        $this->sessionService->shouldReceive('verifySession')->with('sess_1')->andReturnFalse();
        $this->transactionLockService->shouldNotReceive('lockOrder');
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->expectException(UnauthorizedException::class);

        $this->handler->handle('o_x');
    }

    private function order(OrderStatus $status): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(41)
            ->setEventId(7)
            ->setSessionId('sess_1')
            ->setStatus($status->name)
            ->setReservedUntil(now()->addMinutes(10)->toDateTimeString());
    }
}
