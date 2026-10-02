<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\Terminal;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\DomainObjects\StripeTerminalReaderDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\BoxOfficeSaleExpiredException;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalReaderUnavailableException;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\StripeTerminalReaderRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO\TerminalContextDTO;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalContextService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalLocationService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\StripeTerminalPaymentService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\TerminalAttemptTracker;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\TerminalFailureResolver;
use HiEvents\Exceptions\PaymentRefundedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\NotCompletableOrderRefundDTO;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentCreationService;
use HiEvents\Services\Domain\Payment\Stripe\StripeRefundExpiredOrderService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Stripe\PaymentIntent;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\Terminal\ReaderService;
use Stripe\StripeClient;
use Stripe\Terminal\Reader;
use Tests\TestCase;

final class RefundedIntents
{
    /** @var list<string> */
    public array $ids = [];
}

final readonly class KnownRefundsExpiredOrderService extends StripeRefundExpiredOrderService
{
    public function __construct(private RefundedIntents $refunded) {}

    public function hasRefunded(int $orderId, string $paymentIntentId): bool
    {
        return in_array($paymentIntentId, $this->refunded->ids, true);
    }
}

class StripeTerminalPaymentServiceTest extends TestCase
{
    private const ORDER_ID = 41;

    private MockInterface|OrderRepositoryInterface $orderRepository;

    private MockInterface|PaymentIntentService $paymentIntents;

    private MockInterface|ReaderService $readers;

    private MockInterface|StripePaymentsRepository $stripePaymentsRepository;

    private MockInterface|PaymentIntentSucceededHandler $paymentIntentSucceededHandler;

    private RefundedIntents $refundedIntents;

    private StripeTerminalPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-23 12:00:00');

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());
        $databaseManager->shouldReceive('statement');

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $organizerRepository = Mockery::mock(OrganizerRepositoryInterface::class);
        $organizerRepository->shouldReceive('loadRelation')->andReturnSelf();
        $organizerRepository->shouldReceive('findById')->andReturn((new OrganizerDomainObject)->setId(3));

        $readerRepository = Mockery::mock(StripeTerminalReaderRepositoryInterface::class);
        $readerRepository->shouldReceive('findFirstWhere')->andReturn((new StripeTerminalReaderDomainObject)->setId(9)->setStripeReaderId('tmr_1'));

        $this->paymentIntents = Mockery::mock(PaymentIntentService::class);
        $client = Mockery::mock(StripeClient::class);
        $client->shouldReceive('getService')->with('paymentIntents')->andReturn($this->paymentIntents);
        $this->readers = Mockery::mock(ReaderService::class);
        $client->shouldReceive('getService')->with('terminal')->andReturn(new class($this->readers)
        {
            public function __construct(public ReaderService $readers) {}
        });

        $this->stripePaymentsRepository = Mockery::mock(StripePaymentsRepository::class);
        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('updateWhere')->byDefault();

        $locationService = Mockery::mock(StripeTerminalLocationService::class);
        $locationService->shouldReceive('resolve')->andReturn('tml_door');

        $attemptTracker = Mockery::mock(TerminalAttemptTracker::class);
        $attemptTracker->shouldReceive('start')->byDefault();

        $contextService = Mockery::mock(StripeTerminalContextService::class);
        $contextService->shouldReceive('forOrganizer')->andReturn(new TerminalContextDTO(
            client: $client,
            platform: null,
            stripe_account_id: null,
            organizer_stripe_platform_id: null,
        ));

        $this->paymentIntentSucceededHandler = Mockery::mock(PaymentIntentSucceededHandler::class);
        $this->refundedIntents = new RefundedIntents;

        $cardLock = Mockery::mock(Lock::class);
        $cardLock->shouldReceive('get')->andReturn(true);
        $cardLock->shouldReceive('release');
        $cache = Mockery::mock(Cache::class);
        $cache->shouldReceive('lock')->andReturn($cardLock);

        $this->service = new StripeTerminalPaymentService(
            databaseManager: $databaseManager,
            orderRepository: $this->orderRepository,
            organizerRepository: $organizerRepository,
            accountRepository: Mockery::mock(AccountRepositoryInterface::class),
            readerRepository: $readerRepository,
            stripePaymentsRepository: $this->stripePaymentsRepository,
            paymentIntentCreationService: Mockery::mock(StripePaymentIntentCreationService::class),
            contextService: $contextService,
            locationService: $locationService,
            paymentIntentSucceededHandler: $this->paymentIntentSucceededHandler,
            failureResolver: Mockery::mock(TerminalFailureResolver::class),
            attemptTracker: $attemptTracker,
            cache: $cache,
            transactionLockService: new TransactionLockService($databaseManager),
            refundExpiredOrderService: new KnownRefundsExpiredOrderService($this->refundedIntents),
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_restarting_a_card_payment_never_holds_the_sale_past_the_cap(): void
    {
        $order = $this->order(createdAt: Carbon::now()->subMinutes(110));
        $this->orderRepository->shouldReceive('findById')->andReturn($order);
        $this->intentWithStatus(PaymentIntent::STATUS_PROCESSING);

        $this->orderRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => Carbon::parse($attributes[OrderDomainObjectAbstract::RESERVED_UNTIL])
                ->equalTo(Carbon::now()->addMinutes(10)));

        $this->assertSame($order, $this->service->ensureProcessing($order, $this->event(), 9));
    }

    public function test_a_young_sale_is_held_for_the_full_reservation(): void
    {
        $order = $this->order(createdAt: Carbon::now()->subMinutes(5));
        $this->orderRepository->shouldReceive('findById')->andReturn($order);
        $this->intentWithStatus(PaymentIntent::STATUS_PROCESSING);

        $this->orderRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => is_string($attributes[OrderDomainObjectAbstract::RESERVED_UNTIL])
                && Carbon::parse($attributes[OrderDomainObjectAbstract::RESERVED_UNTIL])->equalTo(Carbon::now()->addMinutes(30)));

        $this->assertSame($order, $this->service->ensureProcessing($order, $this->event(), 9));
    }

    public function test_charging_an_expired_sale_reports_the_sale_expired_code(): void
    {
        $order = $this->order(createdAt: Carbon::now()->subMinutes(40))->setReservedUntil(Carbon::now()->subMinute()->toDateTimeString());
        $this->orderRepository->shouldReceive('findById')->andReturn($order);
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->expectException(BoxOfficeSaleExpiredException::class);

        $this->service->ensureProcessing($order, $this->event(), 9);
    }

    public function test_releasing_a_cancelled_card_payment_returns_its_intent(): void
    {
        $this->orderRepository->shouldReceive('findById')->andReturn($this->order(createdAt: Carbon::now()));
        $this->intentWithStatus(PaymentIntent::STATUS_CANCELED);

        $this->assertSame('pi_1', $this->service->releaseCardPayment($this->order(createdAt: Carbon::now()), $this->event(), null));
    }

    public function test_a_card_payment_that_completed_the_sale_cannot_be_released(): void
    {
        $this->orderRepository->shouldReceive('findById')->andReturn($this->order(createdAt: Carbon::now()));
        $this->intentWithStatus(PaymentIntent::STATUS_SUCCEEDED);
        $this->paymentIntentBelongsTo('pi_1', $this->order(createdAt: Carbon::now()));
        $this->paymentIntentSucceededHandler->shouldReceive('handleEvent')->once();

        $this->expectException(ResourceConflictException::class);

        $this->service->releaseCardPayment($this->order(createdAt: Carbon::now()), $this->event(), null);
    }

    public function test_a_card_payment_that_was_refunded_instead_of_completing_the_sale_can_be_released(): void
    {
        $this->orderRepository->shouldReceive('findById')->andReturn($this->order(createdAt: Carbon::now()));
        $this->intentWithStatus(PaymentIntent::STATUS_SUCCEEDED);
        $this->paymentIntentBelongsTo('pi_1', $this->order(createdAt: Carbon::now()));
        $this->paymentIntentSucceededHandler->shouldReceive('handleEvent')->once()->andReturnUsing(function () {
            $this->refundedIntents->ids[] = 'pi_1';

            throw new PaymentRefundedException(
                'The sale expired before the payment arrived',
                new NotCompletableOrderRefundDTO(
                    orderId: self::ORDER_ID,
                    refundId: 're_1',
                    amount: 25.0,
                    currency: 'USD',
                    status: 'succeeded',
                    paymentIntentId: 'pi_1',
                ),
            );
        });

        $this->assertSame('pi_1', $this->service->releaseCardPayment($this->order(createdAt: Carbon::now()), $this->event(), null));
    }

    public function test_a_refunded_card_payment_is_never_processed_a_second_time(): void
    {
        $this->orderRepository->shouldReceive('findById')->andReturn($this->order(createdAt: Carbon::now()));
        $this->intentWithStatus(PaymentIntent::STATUS_SUCCEEDED);
        $this->paymentIntentBelongsTo('pi_1', $this->order(createdAt: Carbon::now()));
        $this->refundedIntents->ids[] = 'pi_1';
        $this->paymentIntentSucceededHandler->shouldNotReceive('handleEvent');

        $this->assertSame('pi_1', $this->service->releaseCardPayment($this->order(createdAt: Carbon::now()), $this->event(), null));
    }

    public function test_releasing_a_sale_without_a_card_payment_returns_null(): void
    {
        $order = $this->order(createdAt: Carbon::now())->setStripePayment(null);
        $this->orderRepository->shouldReceive('findById')->andReturn($order);

        $this->assertNull($this->service->releaseCardPayment($order, $this->event(), null));
    }

    public function test_a_reader_busy_with_another_live_sale_is_not_taken_over(): void
    {
        $order = $this->order(createdAt: Carbon::now());
        $this->orderRepository->shouldReceive('findById')->andReturn($order);
        $this->orderRepository->shouldReceive('updateFromArray');
        $this->intentWithStatus(PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD);
        $this->readerProcessing('pi_other');
        $this->paymentIntentBelongsTo('pi_other', $this->order(createdAt: Carbon::now())->setId(99));
        $this->readers->shouldNotReceive('cancelAction');
        $this->readers->shouldNotReceive('processPaymentIntent');

        $this->expectException(TerminalReaderUnavailableException::class);
        $this->expectExceptionMessage('another sale');

        $this->service->ensureProcessing($order, $this->event(), 9);
    }

    public function test_a_prompt_left_behind_by_a_finished_sale_is_cleared_before_charging(): void
    {
        $order = $this->order(createdAt: Carbon::now());
        $this->orderRepository->shouldReceive('findById')->andReturn($order);
        $this->orderRepository->shouldReceive('updateFromArray');
        $this->intentWithStatus(PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD);
        $this->readerProcessing('pi_other');
        $this->paymentIntentBelongsTo('pi_other', $this->order(createdAt: Carbon::now())->setId(99)->setStatus(OrderStatus::ABANDONED->name));
        $this->readers->shouldReceive('cancelAction')->once()->with('tmr_1', [], [])->ordered();
        $this->readers->shouldReceive('processPaymentIntent')->once()->withArgs(fn (string $readerId, array $params) => $params['payment_intent'] === 'pi_1'
            && $params['process_config']['skip_tipping'] === true)->ordered();

        $this->assertSame($order, $this->service->ensureProcessing($order, $this->event(), 9));
    }

    public function test_a_reader_already_prompting_for_this_sale_is_left_to_finish(): void
    {
        $order = $this->order(createdAt: Carbon::now());
        $this->orderRepository->shouldReceive('findById')->andReturn($order);
        $this->orderRepository->shouldReceive('updateFromArray');
        $this->intentWithStatus(PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD);
        $this->readerProcessing('pi_1');
        $this->readers->shouldNotReceive('cancelAction');
        $this->readers->shouldNotReceive('processPaymentIntent');

        $this->assertSame($order, $this->service->ensureProcessing($order, $this->event(), 9));
    }

    public function test_releasing_a_sale_leaves_another_sales_prompt_on_the_reader(): void
    {
        $this->orderRepository->shouldReceive('findById')->andReturn($this->order(createdAt: Carbon::now()));
        $this->intentWithStatus(PaymentIntent::STATUS_CANCELED);
        $this->readerProcessing('pi_other');
        $this->readers->shouldNotReceive('cancelAction');

        $this->assertSame('pi_1', $this->service->releaseCardPayment($this->order(createdAt: Carbon::now()), $this->event(), 9));
    }

    public function test_releasing_a_sale_cancels_its_own_prompt_on_the_reader(): void
    {
        $this->orderRepository->shouldReceive('findById')->andReturn($this->order(createdAt: Carbon::now()));
        $this->intentWithStatus(PaymentIntent::STATUS_CANCELED);
        $this->readerProcessing('pi_1');
        $this->readers->shouldReceive('cancelAction')->once()->with('tmr_1', [], []);

        $this->assertSame('pi_1', $this->service->releaseCardPayment($this->order(createdAt: Carbon::now()), $this->event(), 9));
    }

    private function readerProcessing(string $paymentIntentId): void
    {
        $this->readers->shouldReceive('retrieve')->with('tmr_1', [], [])->andReturn(Reader::constructFrom([
            'id' => 'tmr_1',
            'location' => 'tml_door',
            'action' => [
                'status' => 'in_progress',
                'process_payment_intent' => ['payment_intent' => $paymentIntentId],
            ],
        ]));
    }

    private function paymentIntentBelongsTo(string $paymentIntentId, OrderDomainObject $order): void
    {
        $this->stripePaymentsRepository
            ->shouldReceive('findFirstWhere')
            ->with(['payment_intent_id' => $paymentIntentId])
            ->andReturn((new StripePaymentDomainObject)->setPaymentIntentId($paymentIntentId)->setOrderId($order->getId())->setOrder($order));
    }

    private function intentWithStatus(string $status): void
    {
        $this->paymentIntents->shouldReceive('retrieve')->andReturn(PaymentIntent::constructFrom(['id' => 'pi_1', 'status' => $status]));
    }

    private function order(Carbon $createdAt): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setShortId('o_door')
            ->setEventId(7)
            ->setStatus(OrderStatus::RESERVED->name)
            ->setCurrency('USD')
            ->setTotalGross(25.0)
            ->setCreatedAt($createdAt->toDateTimeString())
            ->setReservedUntil(Carbon::now()->addMinutes(5)->toDateTimeString())
            ->setStripePayment((new StripePaymentDomainObject)->setId(2)->setPaymentIntentId('pi_1'));
    }

    private function event(): EventDomainObject
    {
        return (new EventDomainObject)->setId(7)->setOrganizerId(3);
    }
}
