<?php

namespace Tests\Unit\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentFailedHandler;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentUpdateFromPaymentIntentService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Stripe\PaymentIntent;
use Tests\TestCase;

final readonly class RecordingStripePaymentUpdateService extends StripePaymentUpdateFromPaymentIntentService
{
    public function __construct(private StripePaymentUpdateCallLog $log) {}

    public function updateStripePaymentInfo(PaymentIntent $paymentIntent, StripePaymentDomainObjectAbstract $stripePayment): void
    {
        $this->log->calls++;
    }
}

final class StripePaymentUpdateCallLog
{
    public int $calls = 0;
}

class PaymentIntentFailedHandlerTest extends TestCase
{
    private OrderRepositoryInterface|MockInterface $orderRepository;

    private StripePaymentsRepository|MockInterface $stripePaymentsRepository;

    private StripePaymentUpdateCallLog $updateLog;

    private PaymentIntentFailedHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->stripePaymentsRepository = Mockery::mock(StripePaymentsRepository::class);
        $this->updateLog = new StripePaymentUpdateCallLog;

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $this->handler = new PaymentIntentFailedHandler(
            $this->orderRepository,
            $this->stripePaymentsRepository,
            $databaseManager,
            new RecordingStripePaymentUpdateService($this->updateLog),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_downgrades_an_order_that_is_still_awaiting_payment(): void
    {
        $stripePayment = (new StripePaymentDomainObject)
            ->setOrderId(1)
            ->setPaymentIntentId('pi_test');

        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')->andReturn($stripePayment);

        $this->orderRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                [OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_FAILED->name],
                [
                    OrderDomainObjectAbstract::ID => 1,
                    OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::AWAITING_PAYMENT->name,
                ],
            )
            ->andReturn(1);

        $updatedOrder = (new OrderDomainObject)->setId(1);
        $this->orderRepository->shouldReceive('loadRelation')->with(OrderItemDomainObject::class)->andReturnSelf();
        $this->orderRepository->shouldReceive('findById')->with(1)->andReturn($updatedOrder);

        $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));

        $this->assertSame(1, $this->updateLog->calls);
        Event::assertDispatched(OrderStatusChangedEvent::class);
    }

    public function test_late_failure_does_not_clobber_a_paid_order(): void
    {
        $stripePayment = (new StripePaymentDomainObject)
            ->setOrderId(1)
            ->setPaymentIntentId('pi_test');

        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')->andReturn($stripePayment);

        $this->orderRepository->shouldReceive('updateWhere')->once()->andReturn(0);
        $this->orderRepository->shouldNotReceive('findById');

        $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_test']));

        Event::assertNotDispatched(OrderStatusChangedEvent::class);
    }

    public function test_returns_quietly_when_no_stripe_payment_exists(): void
    {
        $this->stripePaymentsRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->stripePaymentsRepository->shouldReceive('findFirstWhere')->andReturnNull();

        $this->orderRepository->shouldNotReceive('updateWhere');

        $this->handler->handleEvent(PaymentIntent::constructFrom(['id' => 'pi_unknown']));

        $this->assertSame(0, $this->updateLog->calls);
        Event::assertNotDispatched(OrderStatusChangedEvent::class);
    }
}
