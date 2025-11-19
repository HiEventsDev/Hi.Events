<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Repository\Interfaces\OrderPaymentPlatformFeeRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderPaymentPlatformFeeService;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentPlatformFeeExtractionService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Support\Facades\Config;
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

    public function testExtractAndStorePlatformFeeWithVatAndExchangeRate(): void
    {
        Config::set('app.tax.eu_vat_handling_enabled', true);

        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);
        $order->shouldReceive('getCurrency')->andReturn('gbp');

        $stripePayment = m::mock(StripePaymentDomainObject::class);
        $stripePayment->shouldReceive('getApplicationFeeNet')->andReturn(417);
        $stripePayment->shouldReceive('getApplicationFeeVat')->andReturn(83);

        $balanceTransaction = (object)[
            'id' => 'txn_123',
            'fee' => 1000,
            'net' => 9000,
            'currency' => 'eur',
            'exchange_rate' => 1.17,
            'fee_details' => [
                (object)[
                    'type' => 'stripe_fee',
                    'amount' => 500,
                    'currency' => 'eur',
                    'description' => 'Stripe processing fee',
                ],
                (object)[
                    'type' => 'application_fee',
                    'amount' => 500,
                    'currency' => 'eur',
                    'description' => 'Application fee',
                ],
            ],
        ];

        $paymentIntent = (object)[
            'metadata' => [
                'application_fee_gross_amount' => 5.00,
                'application_fee_net_amount' => 4.17,
                'application_fee_vat_amount' => 0.83,
                'application_fee_vat_rate' => 0.20,
            ],
        ];

        $charge = Charge::constructFrom([
            'id' => 'ch_123',
            'balance_transaction' => $balanceTransaction,
            'payment_intent' => $paymentIntent,
            'metadata' => [],
        ]);

        $this->orderPaymentPlatformFeeRepository->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(null);

        $this->logger->shouldReceive('info')
            ->with(__('Extracting platform fee for order'), m::type('array'))
            ->once();

        $this->orderPaymentPlatformFeeService->shouldReceive('createOrderPaymentPlatformFee')
            ->once()
            ->withArgs(function (
                $orderId,
                $paymentPlatform,
                $feeRollup,
                $paymentPlatformFeeAmountMinorUnit,
                $applicationFeeGrossAmountMinorUnit,
                $currency,
                $transactionId,
                $chargeId,
                $applicationFeeNetAmountMinorUnit,
                $applicationFeeVatAmountMinorUnit
            ) {
                return $orderId === 123
                    && $paymentPlatformFeeAmountMinorUnit === 500
                    && $applicationFeeGrossAmountMinorUnit === 500
                    && $chargeId === 'ch_123'
                    && $applicationFeeNetAmountMinorUnit === 488
                    && $applicationFeeVatAmountMinorUnit === 97
                    && $currency === 'eur';
            });

        $this->logger->shouldReceive('info')
            ->with(__('Platform fee stored successfully'), m::type('array'))
            ->once();

        $this->service->extractAndStorePlatformFee($order, $charge, $stripePayment);

        $this->assertTrue(true);
    }

    public function testExtractAndStorePlatformFeeWithVatDisabled(): void
    {
        Config::set('app.tax.eu_vat_handling_enabled', false);

        $order = m::mock(OrderDomainObject::class);
        $order->shouldReceive('getId')->andReturn(123);
        $order->shouldReceive('getCurrency')->andReturn('eur');

        $stripePayment = m::mock(StripePaymentDomainObject::class);
        $stripePayment->shouldReceive('getApplicationFeeNet')->never();
        $stripePayment->shouldReceive('getApplicationFeeVat')->never();

        $balanceTransaction = (object)[
            'id' => 'txn_123',
            'fee' => 1000,
            'net' => 9000,
            'currency' => 'eur',
            'exchange_rate' => null,
            'fee_details' => [
                (object)[
                    'type' => 'stripe_fee',
                    'amount' => 500,
                    'currency' => 'eur',
                    'description' => 'Stripe processing fee',
                ],
                (object)[
                    'type' => 'application_fee',
                    'amount' => 500,
                    'currency' => 'eur',
                    'description' => 'Application fee',
                ],
            ],
        ];

        $charge = Charge::constructFrom([
            'id' => 'ch_123',
            'balance_transaction' => $balanceTransaction,
            'metadata' => [],
        ]);

        $this->orderPaymentPlatformFeeRepository->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(null);

        $this->logger->shouldReceive('info')
            ->with(__('Extracting platform fee for order'), m::type('array'))
            ->once();

        $this->orderPaymentPlatformFeeService->shouldReceive('createOrderPaymentPlatformFee')
            ->once()
            ->withArgs(function (
                $orderId,
                $paymentPlatform,
                $feeRollup,
                $paymentPlatformFeeAmountMinorUnit,
                $applicationFeeGrossAmountMinorUnit,
                $currency,
                $transactionId,
                $chargeId,
                $applicationFeeNetAmountMinorUnit,
                $applicationFeeVatAmountMinorUnit
            ) {
                return $orderId === 123
                    && $paymentPlatformFeeAmountMinorUnit === 500
                    && $applicationFeeGrossAmountMinorUnit === 500
                    && $chargeId === 'ch_123'
                    && $applicationFeeNetAmountMinorUnit === null
                    && $applicationFeeVatAmountMinorUnit === null
                    && $currency === 'eur';
            });

        $this->logger->shouldReceive('info')
            ->with(__('Platform fee stored successfully'), m::type('array'))
            ->once();

        $this->service->extractAndStorePlatformFee($order, $charge, $stripePayment);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
