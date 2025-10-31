<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderPaymentPlatformFeeService;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentPlatformFeeExtractionService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Stripe\Charge;
use Tests\TestCase;

class StripePaymentPlatformFeeExtractionServiceTest extends TestCase
{
    private StripePaymentPlatformFeeExtractionService $service;
    private StripeClientFactory $stripeClientFactory;
    private OrderPaymentPlatformFeeService $orderPaymentPlatformFeeService;
    private OrderPaymentPlatformFeeRepositoryInterface $orderPaymentPlatformFeeRepository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripeClientFactory = m::mock(StripeClientFactory::class);
        $this->orderPaymentPlatformFeeService = m::mock(OrderPaymentPlatformFeeService::class);
        $this->orderPaymentPlatformFeeRepository = m::mock(OrderPaymentPlatformFeeRepositoryInterface::class);
        $this->logger = m::mock(LoggerInterface::class);

        $this->service = new StripePaymentPlatformFeeExtractionService(
            $this->stripeClientFactory,
            $this->orderPaymentPlatformFeeService,
            $this->orderPaymentPlatformFeeRepository,
            $this->logger
        );
    }

    public function testExtractAndStorePlatformFeeNoBalanceTransaction(): void
    {
        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);

        $stripePayment = m::mock(StripePaymentDomainObject::class);
        $stripePayment->shouldReceive('getStripePlatformEnum')->andReturn(null);
        $stripePayment->shouldReceive('getConnectedAccountId')->andReturn(null);

        $charge = Charge::constructFrom([
            'id' => 'ch_123',
            'balance_transaction' => null,
        ]);

        $this->orderPaymentPlatformFeeRepository->shouldReceive('findFirstWhere')
            ->never();

        $this->logger->shouldReceive('info')
            ->with(__('Extracting platform fee for order'), [
                'order_id' => 123,
                'charge_id' => 'ch_123',
            ])
            ->once();

        $this->logger->shouldReceive('info')
            ->with(__('Retrieving balance transaction from Stripe'), [
                'charge_id' => 'ch_123',
                'order_id' => 123,
                'connected_account_id' => null,
                'balance_transaction_type' => 'NULL',
            ])
            ->once();

        $stripeClient = m::mock(\Stripe\StripeClient::class);
        $chargesService = m::mock();
        $stripeClient->charges = $chargesService;

        $this->stripeClientFactory->shouldReceive('createForPlatform')
            ->with(null)
            ->andReturn($stripeClient);

        $chargesService->shouldReceive('retrieve')
            ->with('ch_123', ['expand' => ['balance_transaction']], [])
            ->andReturn(Charge::constructFrom([
                'id' => 'ch_123',
                'balance_transaction' => null,
            ]));

        $this->logger->shouldReceive('warning')
            ->with(__('No balance transaction found for charge'), [
                'charge_id' => 'ch_123',
                'order_id' => 123,
            ])
            ->once();

        $this->orderPaymentPlatformFeeService->shouldNotReceive('createOrderPaymentPlatformFee');

        $this->service->extractAndStorePlatformFee($order, $charge, $stripePayment);

        $this->assertTrue(true);
    }

    public function testExtractAndStorePlatformFeeWithConnectedAccount(): void
    {
        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);

        $stripePayment = m::mock(StripePaymentDomainObject::class);
        $stripePayment->shouldReceive('getStripePlatformEnum')->andReturn(null);
        $stripePayment->shouldReceive('getConnectedAccountId')->andReturn('acct_123');

        $charge = Charge::constructFrom([
            'id' => 'ch_123',
            'balance_transaction' => null,
        ]);

        $this->orderPaymentPlatformFeeRepository->shouldReceive('findFirstWhere')
            ->never();

        $this->logger->shouldReceive('info')
            ->with(__('Extracting platform fee for order'), [
                'order_id' => 123,
                'charge_id' => 'ch_123',
            ])
            ->once();

        $this->logger->shouldReceive('info')
            ->with(__('Retrieving balance transaction from Stripe'), [
                'charge_id' => 'ch_123',
                'order_id' => 123,
                'connected_account_id' => 'acct_123',
                'balance_transaction_type' => 'NULL',
            ])
            ->once();

        $stripeClient = m::mock(\Stripe\StripeClient::class);
        $chargesService = m::mock();
        $stripeClient->charges = $chargesService;

        $this->stripeClientFactory->shouldReceive('createForPlatform')
            ->with(null)
            ->andReturn($stripeClient);

        $chargesService->shouldReceive('retrieve')
            ->with(
                'ch_123',
                ['expand' => ['balance_transaction']],
                ['stripe_account' => 'acct_123']
            )
            ->andReturn(Charge::constructFrom([
                'id' => 'ch_123',
                'balance_transaction' => null,
            ]));

        $this->logger->shouldReceive('warning')
            ->with(__('No balance transaction found for charge'), [
                'charge_id' => 'ch_123',
                'order_id' => 123,
            ])
            ->once();

        $this->orderPaymentPlatformFeeService->shouldNotReceive('createOrderPaymentPlatformFee');

        $this->service->extractAndStorePlatformFee($order, $charge, $stripePayment);

        $this->assertTrue(true);
    }

    public function testExtractAndStorePlatformFeeHandlesException(): void
    {
        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);

        $stripePayment = m::mock(StripePaymentDomainObject::class);
        $stripePayment->shouldReceive('getStripePlatformEnum')->andReturn(null);
        $stripePayment->shouldReceive('getConnectedAccountId')->andReturn(null);

        $charge = Charge::constructFrom([
            'id' => 'ch_123',
            'balance_transaction' => null,
        ]);

        $this->orderPaymentPlatformFeeRepository->shouldReceive('findFirstWhere')
            ->never();

        $this->logger->shouldReceive('info')
            ->with(__('Extracting platform fee for order'), [
                'order_id' => 123,
                'charge_id' => 'ch_123',
            ])
            ->once();

        $this->logger->shouldReceive('info')
            ->with(__('Retrieving balance transaction from Stripe'), [
                'charge_id' => 'ch_123',
                'order_id' => 123,
                'connected_account_id' => null,
                'balance_transaction_type' => 'NULL',
            ])
            ->once();

        $this->stripeClientFactory->shouldReceive('createForPlatform')
            ->with(null)
            ->andThrow(new \Exception('Stripe API error'));

        $this->logger->shouldReceive('error')
            ->with(__('Failed to store platform fee'), m::type('array'))
            ->once();

        $this->orderPaymentPlatformFeeService->shouldNotReceive('createOrderPaymentPlatformFee');

        $this->expectException(\Exception::class);

        $this->service->extractAndStorePlatformFee($order, $charge, $stripePayment);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
