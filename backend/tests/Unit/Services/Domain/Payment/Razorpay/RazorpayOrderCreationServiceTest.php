<?php

namespace Tests\Unit\Services\Domain\Payment\Razorpay;

use Exception;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\Razorpay\CreateOrderFailedException;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderRequestDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\CreateRazorpayOrderResponseDTO;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayOrderCreationService;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientInterface;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Razorpay\Api\Errors\BadRequestError;
use Tests\TestCase;

class RazorpayOrderCreationServiceTest extends TestCase
{
    private LoggerInterface&MockObject $loggerMock;
    private Repository&MockObject $configMock;
    private ConnectionInterface&MockObject $dbMock;
    private OrderApplicationFeeCalculationService&MockObject $feeServiceMock;
    private RazorpayClientFactory&MockObject $factoryMock;
    private RazorpayClientInterface&MockObject $razorpayClientMock;
    private RazorpayOrderCreationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->configMock = $this->createMock(Repository::class);
        $this->dbMock = $this->createMock(ConnectionInterface::class); // <-- Perfectly mockable
        $this->feeServiceMock = $this->createMock(OrderApplicationFeeCalculationService::class);
        $this->factoryMock = $this->createMock(RazorpayClientFactory::class);
        $this->razorpayClientMock = $this->createMock(RazorpayClientInterface::class);

        $this->factoryMock->method('create')->willReturn($this->razorpayClientMock);

        $this->service = new RazorpayOrderCreationService(
            $this->loggerMock,
            $this->configMock,
            $this->dbMock,
            $this->feeServiceMock,
            $this->factoryMock
        );
    }

    #[DataProvider('currencyDataProvider')]
    public function testItCalculatesAmountCorrectlyBasedOnCurrency(
        string $currencyCode,
        int $minorUnit,
        float $floatAmount,
        int $expectedRazorpayAmount
    ): void {
        $dtoMock = $this->createMockedRequestDTO($currencyCode, $minorUnit, $floatAmount);
        
        $expectedRazorpayResponse = (object) [
            'id'       => 'order_123',
            'amount'   => $expectedRazorpayAmount,
            'currency' => $currencyCode,
            'receipt'  => 'SHORT_123'
        ];

        // Database Expectations
        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->once())->method('commit');
        $this->dbMock->expects($this->never())->method('rollBack');

        $this->configMock->method('get')
            ->with('services.razorpay.key_id')
            ->willReturn('test_key_id');

        $this->razorpayClientMock->expects($this->once())
            ->method('createOrder')
            ->with($this->callback(function (array $orderData) use ($expectedRazorpayAmount, $currencyCode) {
                return $orderData['amount'] === $expectedRazorpayAmount 
                    && $orderData['currency'] === $currencyCode
                    && $orderData['receipt'] === 'SHORT_123';
            }))
            ->willReturn($expectedRazorpayResponse);

        $response = $this->service->createOrder($dtoMock);

        $this->assertInstanceOf(CreateRazorpayOrderResponseDTO::class, $response);
        $this->assertEquals('order_123', $response->id);
    }

    public function testItRollsBackAndThrowsCustomExceptionOnRazorpayError(): void
    {
        $dtoMock = $this->createMockedRequestDTO();

        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->never())->method('commit');
        $this->dbMock->expects($this->once())->method('rollBack');

        $this->razorpayClientMock->expects($this->once())
            ->method('createOrder')
            ->willThrowException(new BadRequestError('Invalid amount', 400, 400));

        $this->expectException(CreateOrderFailedException::class);

        $this->service->createOrder($dtoMock);
    }

    public function testItRollsBackAndRethrowsGenericException(): void
    {
        $dtoMock = $this->createMockedRequestDTO();

        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->never())->method('commit');
        $this->dbMock->expects($this->once())->method('rollBack');

        $this->razorpayClientMock->expects($this->once())
            ->method('createOrder')
            ->willThrowException(new Exception('Database connection lost'));

        $this->expectException(Exception::class);

        $this->service->createOrder($dtoMock);
    }

    private function createMockedRequestDTO(
        string $currencyCode = 'INR', 
        int $minorUnit = 50000, 
        float $floatAmount = 500.00
    ): CreateRazorpayOrderRequestDTO {
        $amountMock = $this->createMock(MoneyValue::class); 
        $amountMock->method('toMinorUnit')->willReturn($minorUnit);
        $amountMock->method('toFloat')->willReturn($floatAmount);

        $orderMock = $this->createMock(OrderDomainObject::class); 
        $orderMock->method('getShortId')->willReturn('SHORT_123');
        $orderMock->method('getId')->willReturn(1);
        $orderMock->method('getEventId')->willReturn(99);

        $accountMock = $this->createMock(AccountDomainObject::class); 
        $accountMock->method('getId')->willReturn(5);

        return new CreateRazorpayOrderRequestDTO(
            amount: $amountMock,
            currencyCode: $currencyCode,
            account: $accountMock,
            order: $orderMock
        );
    }

    public static function currencyDataProvider(): array
    {
        return [
            'INR uses minor unit' => ['INR', 50000, 500.00, 50000],
            'USD calculates float' => ['USD', 5000, 45.50, 5000],
        ];
    }
}