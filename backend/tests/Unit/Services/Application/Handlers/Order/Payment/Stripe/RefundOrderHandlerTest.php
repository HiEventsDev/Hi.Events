<?php

namespace Tests\Unit\Services\Application\Handlers\Order\Payment\Stripe;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Mail\Order\OrderRefunded;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\RefundOrderHandler;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Stripe\StripeClient;
use Tests\TestCase;

class RefundOrderHandlerTest extends TestCase
{
    private const EVENT_ID = 10;

    private const ORDER_ID = 20;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private Mailer|MockInterface $mailer;

    private StripePaymentIntentRefundService|MockInterface $refundService;

    private RefundOrderHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->mailer = Mockery::mock(Mailer::class);
        $this->refundService = Mockery::mock(StripePaymentIntentRefundService::class);

        $orderCancelService = Mockery::mock(OrderCancelService::class);

        $stripeClientFactory = Mockery::mock(StripeClientFactory::class);
        $stripeClientFactory->shouldReceive('createForPlatform')->andReturn(Mockery::mock(StripeClient::class));

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->handler = new RefundOrderHandler(
            $this->refundService,
            $this->orderRepository,
            $this->eventRepository,
            $this->mailer,
            $orderCancelService,
            $databaseManager,
            $stripeClientFactory,
        );
    }

    public function test_the_buyer_is_notified_when_requested(): void
    {
        $order = $this->givenOrderIsFound(email: 'buyer@example.com');
        $this->givenEventIsFound();
        $this->givenRefundSucceeds();

        $this->mailer->shouldReceive('to')->once()->with('buyer@example.com')->andReturnSelf();
        $this->mailer->shouldReceive('locale')->once()->andReturnSelf();
        $this->mailer->shouldReceive('send')->once()->with(Mockery::type(OrderRefunded::class));

        $this->assertSame($order, $this->handler->handle($this->givenDTO(notifyBuyer: true)));
    }

    public function test_a_box_office_order_without_an_email_is_refunded_without_notifying_the_buyer(): void
    {
        $order = $this->givenOrderIsFound(email: null);
        $this->givenEventIsFound();
        $this->givenRefundSucceeds();

        $this->mailer->shouldNotReceive('to');
        $this->mailer->shouldNotReceive('send');

        $this->assertSame($order, $this->handler->handle($this->givenDTO(notifyBuyer: true)));
    }

    private function givenDTO(bool $notifyBuyer = false): RefundOrderDTO
    {
        return new RefundOrderDTO(
            event_id: self::EVENT_ID,
            order_id: self::ORDER_ID,
            amount: 50.0,
            notify_buyer: $notifyBuyer,
            cancel_order: false,
        );
    }

    private function givenOrderIsFound(?string $email): OrderDomainObject
    {
        $order = (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setEventId(self::EVENT_ID)
            ->setCurrency('USD')
            ->setEmail($email)
            ->setLocale('en')
            ->setTotalGross(100.0)
            ->setTotalRefunded(0.0)
            ->setStripePayment(new StripePaymentDomainObject);

        $this->orderRepository->shouldReceive('loadRelation')->once()->andReturnSelf();
        $this->orderRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with(['event_id' => self::EVENT_ID, 'id' => self::ORDER_ID])
            ->andReturn($order);

        $this->orderRepository->shouldReceive('updateFromArray')->once()->andReturn($order);

        return $order;
    }

    private function givenEventIsFound(): void
    {
        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getOrganizer')->andReturn(Mockery::mock(OrganizerDomainObject::class));
        $event->shouldReceive('getEventSettings')->andReturn(
            Mockery::mock(EventSettingDomainObject::class)->shouldIgnoreMissing()
        );

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')->once()->with(self::EVENT_ID)->andReturn($event);
    }

    private function givenRefundSucceeds(): void
    {
        $this->refundService->shouldReceive('refundPayment')->once();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
