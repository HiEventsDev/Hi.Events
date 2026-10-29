<?php

namespace Tests\Unit\Services\Application\Handlers\Order\Payment\Stripe;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\RefundOrderHandler;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Values\MoneyValue;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Stripe\Refund;
use Stripe\StripeClient;
use Tests\TestCase;

class RefundOrderHandlerTest extends TestCase
{
    private const EVENT_ID = 10;

    private const ORDER_ID = 20;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private OrderRefundRepositoryInterface|MockInterface $orderRefundRepository;

    private StripePaymentIntentRefundService|MockInterface $refundService;

    private RefundOrderHandler $handler;

    private array $refundRequest = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderRefundRepository = Mockery::mock(OrderRefundRepositoryInterface::class);
        $this->refundService = Mockery::mock(StripePaymentIntentRefundService::class);

        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForPlatform')->andReturn(Mockery::mock(StripeClient::class));

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->handler = new RefundOrderHandler(
            $this->refundService,
            $this->orderRepository,
            $this->orderRefundRepository,
            Mockery::mock(OrderCancelService::class),
            $databaseManager,
            $stripeClientFactory,
        );
    }

    public function test_the_buyer_is_flagged_for_the_refund_email_instead_of_being_emailed_straight_away(): void
    {
        $order = $this->givenOrderIsFound(email: 'buyer@example.com');
        $this->givenFailedRefunds(0);
        $this->givenStripeAcceptsTheRefund(Refund::constructFrom(['id' => 're_1', 'status' => 'pending']));

        $this->assertSame($order, $this->handler->handle($this->givenDTO(notifyBuyer: true)));

        $this->assertTrue($this->refundRequest['notifyBuyer']);
    }

    public function test_the_buyer_is_not_flagged_when_the_organizer_did_not_ask_for_the_email(): void
    {
        $this->givenOrderIsFound(email: 'buyer@example.com');
        $this->givenFailedRefunds(0);
        $this->givenStripeAcceptsTheRefund();

        $this->handler->handle($this->givenDTO(notifyBuyer: false));

        $this->assertFalse($this->refundRequest['notifyBuyer']);
    }

    public function test_a_box_office_order_without_an_email_is_refunded_without_flagging_the_buyer(): void
    {
        $order = $this->givenOrderIsFound(email: null);
        $this->givenFailedRefunds(0);
        $this->givenStripeAcceptsTheRefund();

        $this->assertSame($order, $this->handler->handle($this->givenDTO(notifyBuyer: true)));

        $this->assertFalse($this->refundRequest['notifyBuyer']);
    }

    public function test_the_refund_carries_the_amount_already_refunded_and_the_failed_attempts_so_far(): void
    {
        $this->givenOrderIsFound(
            email: null,
            totalGross: 40.0,
            totalRefunded: 20.0,
            refundStatus: OrderRefundStatus::PARTIALLY_REFUNDED->name,
        );
        $this->givenFailedRefunds(1);
        $this->givenStripeAcceptsTheRefund();

        $this->handler->handle($this->givenDTO(amount: 20.0));

        $this->assertSame(2000, $this->refundRequest['amount']->toMinorUnit());
        $this->assertSame(2000, $this->refundRequest['alreadyRefunded']->toMinorUnit());
        $this->assertSame('USD', $this->refundRequest['alreadyRefunded']->getMoney()->getCurrency()->getCurrencyCode());
        $this->assertSame(1, $this->refundRequest['failedRefundCount']);
    }

    public function test_a_second_refund_is_refused_while_one_is_pending(): void
    {
        $order = (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setEventId(self::EVENT_ID)
            ->setCurrency('USD')
            ->setRefundStatus(OrderRefundStatus::REFUND_PENDING->name)
            ->setStripePayment(new StripePaymentDomainObject);

        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('findFirstWhere')->andReturn($order);
        $this->orderRepository->shouldNotReceive('updateFromArray');
        $this->refundService->shouldNotReceive('refundPayment');

        $this->expectException(RefundNotPossibleException::class);

        $this->handler->handle($this->givenDTO());
    }

    public function test_the_order_is_marked_refund_pending_before_the_stripe_refund_is_created(): void
    {
        $calls = [];

        $this->givenOrderIsFound(email: null, onUpdate: function (int $id, array $attributes) use (&$calls) {
            $calls[] = 'mark:'.$attributes[OrderDomainObjectAbstract::REFUND_STATUS];
        });
        $this->givenFailedRefunds(0);

        $this->refundService->shouldReceive('refundPayment')->once()->andReturnUsing(function () use (&$calls) {
            $calls[] = 'stripe-refund';

            return Refund::constructFrom(['id' => 're_1', 'status' => 'pending']);
        });

        $this->handler->handle($this->givenDTO());

        $this->assertSame(['mark:'.OrderRefundStatus::REFUND_PENDING->name, 'stripe-refund'], $calls);
    }

    private function givenDTO(bool $notifyBuyer = false, float $amount = 50.0): RefundOrderDTO
    {
        return new RefundOrderDTO(
            event_id: self::EVENT_ID,
            order_id: self::ORDER_ID,
            amount: $amount,
            notify_buyer: $notifyBuyer,
            cancel_order: false,
        );
    }

    private function givenOrderIsFound(
        ?string $email,
        ?callable $onUpdate = null,
        float $totalGross = 100.0,
        float $totalRefunded = 0.0,
        ?string $refundStatus = null,
    ): OrderDomainObject {
        $order = (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setEventId(self::EVENT_ID)
            ->setCurrency('USD')
            ->setEmail($email)
            ->setLocale('en')
            ->setTotalGross($totalGross)
            ->setTotalRefunded($totalRefunded)
            ->setRefundStatus($refundStatus)
            ->setStripePayment(new StripePaymentDomainObject);

        $this->orderRepository->shouldReceive('loadRelation')->once()->andReturnSelf();
        $this->orderRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['event_id' => self::EVENT_ID, 'id' => self::ORDER_ID])
            ->andReturn($order);

        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->andReturnUsing(function (int $id, array $attributes) use ($order, $onUpdate) {
                if ($onUpdate) {
                    $onUpdate($id, $attributes);
                }

                return $order;
            });

        return $order;
    }

    private function givenFailedRefunds(int $count): void
    {
        $this->orderRefundRepository->shouldReceive('countWhere')
            ->once()
            ->with([
                'order_id' => self::ORDER_ID,
                ['status', 'in', [Refund::STATUS_FAILED, Refund::STATUS_CANCELED]],
            ])
            ->andReturn($count);
    }

    private function givenStripeAcceptsTheRefund(?Refund $refund = null): void
    {
        $this->refundService->shouldReceive('refundPayment')
            ->once()
            ->andReturnUsing(function (
                MoneyValue $amount,
                MoneyValue $alreadyRefunded,
                int $failedRefundCount,
                StripePaymentDomainObject $payment,
                StripeClient $stripeClient,
                bool $notifyBuyer,
            ) use ($refund) {
                $this->refundRequest = compact('amount', 'alreadyRefunded', 'failedRefundCount', 'notifyBuyer');

                return $refund ?? Refund::constructFrom(['id' => 're_1', 'status' => 'pending']);
            });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
