<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PayoutPaidHandler;
use HiEvents\Services\Domain\Payment\Stripe\StripePayoutService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Stripe\ApplicationFee;
use Stripe\BalanceTransaction;
use Stripe\Collection;
use Stripe\Payout;
use Stripe\StripeClient;
use Tests\TestCase;

class PayoutPaidHandlerTest extends TestCase
{
    private PayoutPaidHandler $handler;
    private StripePaymentsRepository $stripePaymentsRepository;
    private StripeClientFactory $stripeClientFactory;
    private LoggerInterface $logger;
    private $stripeConfigurationService;
    private StripePayoutService $stripePayoutService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripePaymentsRepository = m::mock(StripePaymentsRepository::class);
        $this->stripeClientFactory = m::mock(StripeClientFactory::class);
        $this->logger = m::mock(LoggerInterface::class);
        $this->stripeConfigurationService = m::mock(StripeConfigurationService::class);
        $this->stripePayoutService = m::mock(StripePayoutService::class);

        $this->handler = new PayoutPaidHandler(
            $this->stripePaymentsRepository,
            $this->stripeClientFactory,
            $this->logger,
            $this->stripeConfigurationService,
            $this->stripePayoutService
        );
    }

    public function testHandleEventReconcilesPayout(): void
    {
        $payout = Payout::constructFrom([
            'id' => 'po_123',
            'amount' => 10000,
            'currency' => 'eur',
            'status' => 'paid',
        ]);

        $appFee1 = ApplicationFee::constructFrom([
            'id' => 'fee_123',
            'charge' => 'ch_123',
        ]);

        $appFee2 = ApplicationFee::constructFrom([
            'id' => 'fee_456',
            'charge' => 'ch_456',
        ]);

        $balanceTxn1 = BalanceTransaction::constructFrom([
            'id' => 'txn_123',
            'type' => 'application_fee',
            'source' => $appFee1,
            'amount' => 585,
            'fee' => 50,
            'net' => 535,
            'currency' => 'eur',
            'exchange_rate' => null,
        ]);

        $balanceTxn2 = BalanceTransaction::constructFrom([
            'id' => 'txn_456',
            'type' => 'application_fee',
            'source' => $appFee2,
            'amount' => 1170,
            'fee' => 100,
            'net' => 1070,
            'currency' => 'eur',
            'exchange_rate' => null,
        ]);

        $transactions = Collection::constructFrom([
            'data' => [$balanceTxn1, $balanceTxn2],
            'has_more' => false,
        ]);

        $stripeClient = m::mock(StripeClient::class);
        $balanceTransactionsService = m::mock();
        $stripeClient->balanceTransactions = $balanceTransactionsService;

        $this->stripeConfigurationService->shouldReceive('getPrimaryPlatform')
            ->andReturn(null);

        $this->stripeClientFactory->shouldReceive('createForPlatform')
            ->with(null)
            ->andReturn($stripeClient);

        $balanceTransactionsService->shouldReceive('all')
            ->with([
                'payout' => 'po_123',
                'limit' => 100,
                'expand' => ['data.source'],
            ])
            ->andReturn($transactions);

        $stripePayment1 = m::mock(StripePaymentDomainObject::class);
        $stripePayment1->shouldReceive('getId')->andReturn(1);
        $stripePayment1->shouldReceive('getChargeId')->andReturn('ch_123');

        $stripePayment2 = m::mock(StripePaymentDomainObject::class);
        $stripePayment2->shouldReceive('getId')->andReturn(2);
        $stripePayment2->shouldReceive('getChargeId')->andReturn('ch_456');

        $this->stripePaymentsRepository->shouldReceive('findWhereIn')
            ->with(StripePaymentDomainObjectAbstract::CHARGE_ID, ['ch_123', 'ch_456'])
            ->andReturn(collect([$stripePayment1, $stripePayment2]));

        $this->stripePaymentsRepository->shouldReceive('updateWhere')
            ->with(
                m::on(fn($attrs) =>
                    $attrs[StripePaymentDomainObjectAbstract::PAYOUT_ID] === 'po_123' &&
                    $attrs[StripePaymentDomainObjectAbstract::BALANCE_TRANSACTION_ID] === 'txn_123' &&
                    $attrs[StripePaymentDomainObjectAbstract::PAYOUT_STRIPE_FEE] === 50 &&
                    $attrs[StripePaymentDomainObjectAbstract::PAYOUT_NET_AMOUNT] === 535.0 &&
                    $attrs[StripePaymentDomainObjectAbstract::PAYOUT_CURRENCY] === 'EUR' &&
                    $attrs[StripePaymentDomainObjectAbstract::PAYOUT_EXCHANGE_RATE] === null
                ),
                [StripePaymentDomainObjectAbstract::ID => 1]
            )
            ->once();

        $this->stripePaymentsRepository->shouldReceive('updateWhere')
            ->with(
                m::on(fn($attrs) =>
                    $attrs[StripePaymentDomainObjectAbstract::PAYOUT_ID] === 'po_123' &&
                    $attrs[StripePaymentDomainObjectAbstract::BALANCE_TRANSACTION_ID] === 'txn_456' &&
                    $attrs[StripePaymentDomainObjectAbstract::PAYOUT_STRIPE_FEE] === 100 &&
                    $attrs[StripePaymentDomainObjectAbstract::PAYOUT_NET_AMOUNT] === 1070.0 &&
                    $attrs[StripePaymentDomainObjectAbstract::PAYOUT_CURRENCY] === 'EUR' &&
                    $attrs[StripePaymentDomainObjectAbstract::PAYOUT_EXCHANGE_RATE] === null
                ),
                [StripePaymentDomainObjectAbstract::ID => 2]
            )
            ->once();

        $this->logger->shouldReceive('info')->atLeast()->once();
        $this->logger->shouldReceive('debug')->atLeast()->once();
        $this->logger->shouldReceive('error')->never();

        $this->stripePayoutService->shouldReceive('createOrUpdatePayout')
            ->once()
            ->with(m::on(function($dto) {
                $this->assertEquals('po_123', $dto->payoutId);
                return true;
            }));

        $this->handler->handleEvent($payout);

        $this->assertTrue(true);
    }

    public function testHandleEventSkipsNonPaidPayout(): void
    {
        $payout = Payout::constructFrom([
            'id' => 'po_123',
            'amount' => 10000,
            'currency' => 'eur',
            'status' => 'pending',
        ]);

        $this->logger->shouldReceive('info')->twice();

        $this->stripeConfigurationService->shouldNotReceive('getPrimaryPlatform');
        $this->stripeClientFactory->shouldNotReceive('createForPlatform');
        $this->stripePaymentsRepository->shouldNotReceive('findWhereIn');
        $this->stripePaymentsRepository->shouldNotReceive('updateWhere');
        $this->stripePayoutService->shouldNotReceive('createOrUpdatePayout');

        $this->handler->handleEvent($payout);

        $this->assertTrue(true);
    }

    public function testHandleEventSkipsTransactionsWithNoChargeId(): void
    {
        $payout = Payout::constructFrom([
            'id' => 'po_123',
            'amount' => 10000,
            'currency' => 'eur',
            'status' => 'paid',
        ]);

        $appFee = ApplicationFee::constructFrom([
            'id' => 'fee_123',
            'charge' => null,
            'originating_transaction' => null,
        ]);

        $balanceTxn = BalanceTransaction::constructFrom([
            'id' => 'txn_123',
            'type' => 'application_fee',
            'source' => $appFee,
            'amount' => 585,
            'fee' => 50,
            'net' => 535,
            'currency' => 'eur',
        ]);

        $transactions = Collection::constructFrom([
            'data' => [$balanceTxn],
            'has_more' => false,
        ]);

        $stripeClient = m::mock(StripeClient::class);
        $balanceTransactionsService = m::mock();
        $stripeClient->balanceTransactions = $balanceTransactionsService;

        $this->stripeConfigurationService->shouldReceive('getPrimaryPlatform')
            ->andReturn(null);

        $this->stripeClientFactory->shouldReceive('createForPlatform')
            ->with(null)
            ->andReturn($stripeClient);

        $balanceTransactionsService->shouldReceive('all')
            ->with([
                'payout' => 'po_123',
                'limit' => 100,
                'expand' => ['data.source'],
            ])
            ->andReturn($transactions);

        $this->logger->shouldReceive('info')->twice();
        $this->logger->shouldReceive('debug')->twice();

        $this->stripePaymentsRepository->shouldNotReceive('findWhereIn');
        $this->stripePaymentsRepository->shouldNotReceive('updateWhere');

        $this->stripePayoutService->shouldReceive('createOrUpdatePayout')
            ->once()
            ->with(m::on(fn($dto) => $dto->payoutId === 'po_123'));

        $this->handler->handleEvent($payout);

        $this->assertTrue(true);
    }

    public function testHandleEventLogsWarningWhenPaymentNotFound(): void
    {
        $payout = Payout::constructFrom([
            'id' => 'po_123',
            'amount' => 10000,
            'currency' => 'eur',
            'status' => 'paid',
        ]);

        $appFee = ApplicationFee::constructFrom([
            'id' => 'fee_123',
            'charge' => 'ch_123',
        ]);

        $balanceTxn = BalanceTransaction::constructFrom([
            'id' => 'txn_123',
            'type' => 'application_fee',
            'source' => $appFee,
            'amount' => 585,
            'fee' => 50,
            'net' => 535,
            'currency' => 'eur',
        ]);

        $transactions = Collection::constructFrom([
            'data' => [$balanceTxn],
            'has_more' => false,
        ]);

        $stripeClient = m::mock(StripeClient::class);
        $balanceTransactionsService = m::mock();
        $stripeClient->balanceTransactions = $balanceTransactionsService;

        $this->stripeConfigurationService->shouldReceive('getPrimaryPlatform')
            ->andReturn(null);

        $this->stripeClientFactory->shouldReceive('createForPlatform')
            ->with(null)
            ->andReturn($stripeClient);

        $balanceTransactionsService->shouldReceive('all')
            ->with([
                'payout' => 'po_123',
                'limit' => 100,
                'expand' => ['data.source'],
            ])
            ->andReturn($transactions);

        $this->stripePaymentsRepository->shouldReceive('findWhereIn')
            ->with(StripePaymentDomainObjectAbstract::CHARGE_ID, ['ch_123'])
            ->andReturn(collect([]));

        $this->logger->shouldReceive('info')->twice();
        $this->logger->shouldReceive('debug')->twice();
        $this->logger->shouldReceive('warning')->once();

        $this->stripePaymentsRepository->shouldNotReceive('updateWhere');

        $this->stripePayoutService->shouldReceive('createOrUpdatePayout')
            ->once()
            ->with(m::on(fn($dto) => $dto->payoutId === 'po_123'));

        $this->handler->handleEvent($payout);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
