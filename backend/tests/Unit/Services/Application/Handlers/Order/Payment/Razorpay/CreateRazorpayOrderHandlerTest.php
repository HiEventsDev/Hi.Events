<?php

namespace Tests\Unit\Services\Application\Handlers\Order\Payment\Razorpay;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\CreateRazorpayOrderHandler;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderRequestDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderResponseDTO;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayOrderCreationService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Config\Repository;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class CreateRazorpayOrderHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orderRepoMock;
    private RazorpayOrderCreationService&MockObject $razorpayOrderServiceMock;
    private CheckoutSessionManagementService&MockObject $sessionServiceMock;
    private RazorpayOrdersRepositoryInterface&MockObject $razorpayOrdersRepoMock;
    private AccountRepositoryInterface&MockObject $accountRepoMock;
    private Repository&MockObject $configMock;
    private CreateRazorpayOrderHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepoMock = $this->createMock(OrderRepositoryInterface::class);
        $this->razorpayOrderServiceMock = $this->createMock(RazorpayOrderCreationService::class);
        $this->sessionServiceMock = $this->createMock(CheckoutSessionManagementService::class);
        $this->razorpayOrdersRepoMock = $this->createMock(RazorpayOrdersRepositoryInterface::class);
        $this->accountRepoMock = $this->createMock(AccountRepositoryInterface::class);
        $this->configMock = $this->createMock(Repository::class);

        // Handle fluent interface chaining consistently
        $this->orderRepoMock->method('loadRelation')->willReturnSelf();
        $this->accountRepoMock->method('loadRelation')->willReturnSelf();

        $this->handler = new CreateRazorpayOrderHandler(
            $this->orderRepoMock,
            $this->razorpayOrderServiceMock,
            $this->sessionServiceMock,
            $this->razorpayOrdersRepoMock,
            $this->accountRepoMock,
            $this->configMock
        );
    }

    // -------------------------------------------------------------------------
    // UNHAPPY PATHS
    // -------------------------------------------------------------------------

    public function testItThrowsExceptionIfOrderNotFound(): void
    {
        $this->orderRepoMock->expects($this->once())
            ->method('findByShortId')
            ->with('SHORT_123')
            ->willReturn(null);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Sorry, we could not verify your session. Please create a new order.');

        $this->handler->handle('SHORT_123');
    }

    public function testItThrowsExceptionIfSessionIsInvalid(): void
    {
        $orderMock = $this->createMockedOrder();

        $this->orderRepoMock->method('findByShortId')->willReturn($orderMock);

        $this->sessionServiceMock->expects($this->once())
            ->method('verifySession')
            ->with('session_abc')
            ->willReturn(false);

        $this->expectException(UnauthorizedException::class);

        $this->handler->handle('SHORT_123');
    }

    public function testItThrowsExceptionIfOrderIsNotReservedOrIsExpired(): void
    {
        // Use helper to quickly generate an expired order
        $orderMock = $this->createMockedOrder(OrderStatus::COMPLETED->name, true);

        $this->orderRepoMock->method('findByShortId')->willReturn($orderMock);
        $this->sessionServiceMock->method('verifySession')->willReturn(true);

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle('SHORT_123');
    }

    // -------------------------------------------------------------------------
    // HAPPY PATHS
    // -------------------------------------------------------------------------

    public function testItReturnsEarlyIfRazorpayOrderAlreadyExists(): void
    {
        // 1. Arrange Existing Razorpay Data
        $razorpayOrderMock = $this->createMock(RazorpayOrderDomainObject::class);
        $razorpayOrderMock->method('getRazorpayOrderId')->willReturn('rp_existing_123');
        $razorpayOrderMock->method('getAmount')->willReturn(50000);
        $razorpayOrderMock->method('getCurrency')->willReturn('INR');

        // Use helper to build order WITH the existing Razorpay object attached
        $orderMock = $this->createMockedOrder(OrderStatus::RESERVED->name, false, $razorpayOrderMock);

        $this->orderRepoMock->method('findByShortId')->willReturn($orderMock);
        $this->sessionServiceMock->method('verifySession')->willReturn(true);
        $this->accountRepoMock->method('findByEventId')->willReturn($this->createMock(AccountDomainObject::class));

        $this->configMock->method('get')
            ->with('services.razorpay.key_id')
            ->willReturn('test_key_123');

        // Verify we strictly avoid calling the external service or DB
        $this->razorpayOrderServiceMock->expects($this->never())->method('createOrder');
        $this->razorpayOrdersRepoMock->expects($this->never())->method('create');

        // 2. Act
        $response = $this->handler->handle('SHORT_123');

        // 3. Assert
        $this->assertInstanceOf(CreateRazorpayOrderResponseDTO::class, $response);
        $this->assertEquals('rp_existing_123', $response->id);
    }

    public function testItCreatesNewRazorpayOrderSuccessfully(): void
    {
        // 1. Arrange Order & Account
        $orderMock = $this->createMockedOrder(); // Defaults to valid/reserved
        $accountMock = $this->createMock(AccountDomainObject::class);

        $this->orderRepoMock->method('findByShortId')->willReturn($orderMock);
        $this->sessionServiceMock->method('verifySession')->willReturn(true);
        $this->accountRepoMock->method('findByEventId')->willReturn($accountMock);

        $this->configMock->method('get')
            ->with('services.razorpay.key_id')
            ->willReturn('test_key_123');

        $expectedServiceResponse = new CreateRazorpayOrderResponseDTO(
            id: 'rp_new_123',
            keyId: 'test_key_123',
            amount: 50000,
            currency: 'INR'
        );

        // 2. Assert Service is Called with correctly mapped DTO
        $this->razorpayOrderServiceMock->expects($this->once())
            ->method('createOrder')
            ->with($this->callback(function (CreateRazorpayOrderRequestDTO $dto) use ($accountMock, $orderMock) {
                return $dto->currencyCode === 'INR'
                    && $dto->account === $accountMock
                    && $dto->order === $orderMock;
            }))
            ->willReturn($expectedServiceResponse);

        // 3. Assert Repository Creates Record
        $this->razorpayOrdersRepoMock->expects($this->once())
            ->method('create')
            ->with([
                'order_id' => 10,
                'razorpay_order_id' => 'rp_new_123',
                'amount' => 50000,
                'currency' => 'INR',
                'receipt' => 'SHORT_123',
            ]);

        // 4. Act
        $response = $this->handler->handle('SHORT_123');

        // 5. Assert Response
        $this->assertInstanceOf(CreateRazorpayOrderResponseDTO::class, $response);
        $this->assertEquals('rp_new_123', $response->id);
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /**
     * Helper method to keep test bodies clean and consistent.
     */
    private function createMockedOrder(
        string $status = 'RESERVED', // Using string default to avoid enum lookup in signature
        bool $isExpired = false,
        ?RazorpayOrderDomainObject $razorpayOrder = null
    ): OrderDomainObject&MockObject {
        $orderMock = $this->createMock(OrderDomainObject::class);
        $orderMock->method('getSessionId')->willReturn('session_abc');
        $orderMock->method('getStatus')->willReturn($status);
        $orderMock->method('isReservedOrderExpired')->willReturn($isExpired);
        $orderMock->method('getEventId')->willReturn(99);
        $orderMock->method('getRazorpayOrder')->willReturn($razorpayOrder);
        $orderMock->method('getTotalGross')->willReturn(500.00);
        $orderMock->method('getCurrency')->willReturn('INR');
        $orderMock->method('getId')->willReturn(10);
        $orderMock->method('getShortId')->willReturn('SHORT_123');

        return $orderMock;
    }
}