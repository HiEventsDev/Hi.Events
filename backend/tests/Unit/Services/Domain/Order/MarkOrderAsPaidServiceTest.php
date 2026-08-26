<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\InvoiceStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Mail\SendOrderDetailsService;
use HiEvents\Services\Domain\Order\MarkOrderAsPaidService;
use HiEvents\Services\Domain\Order\OccurrenceStatusValidator;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class MarkOrderAsPaidServiceTest extends TestCase
{
    private const ORDER_ID = 100;

    private const EVENT_ID = 200;

    private const AFFILIATE_ID = 300;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private AffiliateRepositoryInterface|MockInterface $affiliateRepository;

    private InvoiceRepositoryInterface|MockInterface $invoiceRepository;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private DomainEventDispatcherService|MockInterface $domainEventDispatcherService;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private OrderApplicationFeeService|MockInterface $orderApplicationFeeService;

    private SendOrderDetailsService|MockInterface $sendOrderDetailsService;

    private OccurrenceStatusValidator|MockInterface $occurrenceStatusValidator;

    private MarkOrderAsPaidService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->affiliateRepository = Mockery::mock(AffiliateRepositoryInterface::class);
        $this->invoiceRepository = Mockery::mock(InvoiceRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->orderApplicationFeeService = Mockery::mock(OrderApplicationFeeService::class);
        $this->sendOrderDetailsService = Mockery::mock(SendOrderDetailsService::class);
        $this->occurrenceStatusValidator = Mockery::mock(OccurrenceStatusValidator::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->service = new MarkOrderAsPaidService(
            $this->orderRepository,
            $databaseManager,
            $this->affiliateRepository,
            $this->invoiceRepository,
            $this->attendeeRepository,
            $this->domainEventDispatcherService,
            Mockery::mock(OrderApplicationFeeCalculationService::class),
            $this->eventRepository,
            $this->orderApplicationFeeService,
            $this->sendOrderDetailsService,
            $this->occurrenceStatusValidator,
        );
    }

    public function test_marking_an_awaiting_offline_order_as_paid_completes_it_and_fires_the_completion_event(): void
    {
        $this->givenOrderAwaitingOfflinePayment();
        $this->givenEventWithoutOrganizerConfiguration();
        $this->invoiceRepository->shouldReceive('findLatestInvoiceForOrder')->once()->with(self::ORDER_ID)->andReturnNull();

        $this->orderRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(self::ORDER_ID, [
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_RECEIVED->name,
            ]);

        $this->attendeeRepository->shouldReceive('updateWhere')
            ->once()
            ->with(
                ['status' => AttendeeStatus::ACTIVE->name],
                ['order_id' => self::ORDER_ID, 'status' => AttendeeStatus::AWAITING_PAYMENT->name],
            );

        $this->affiliateRepository->shouldNotReceive('incrementSales');

        $this->domainEventDispatcherService->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(
                fn (OrderEvent $event) => $event->type === DomainEventType::ORDER_MARKED_AS_PAID
                    && $event->orderId === self::ORDER_ID
            ));

        $this->sendOrderDetailsService->shouldReceive('sendCustomerOrderSummary')->once();

        $result = $this->service->markOrderAsPaid(self::ORDER_ID, self::EVENT_ID);

        $this->assertSame(OrderStatus::COMPLETED->name, $result->getStatus());

        Event::assertDispatched(
            OrderStatusChangedEvent::class,
            fn (OrderStatusChangedEvent $event) => $event->order->getId() === self::ORDER_ID
                && $event->order->isOrderCompleted()
                && $event->sendEmails === false
        );
    }

    public function test_affiliate_sales_are_credited_with_the_order_gross_when_marked_as_paid(): void
    {
        $this->givenOrderAwaitingOfflinePayment(affiliateId: self::AFFILIATE_ID, totalGross: 125.5);
        $this->givenEventWithoutOrganizerConfiguration();
        $this->invoiceRepository->shouldReceive('findLatestInvoiceForOrder')->andReturnNull();
        $this->orderRepository->shouldReceive('updateFromArray')->once();
        $this->attendeeRepository->shouldReceive('updateWhere')->once();
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();
        $this->sendOrderDetailsService->shouldReceive('sendCustomerOrderSummary')->once();

        $this->affiliateRepository->shouldReceive('incrementSales')
            ->once()
            ->with(self::AFFILIATE_ID, 125.5);

        $this->service->markOrderAsPaid(self::ORDER_ID, self::EVENT_ID);
    }

    public function test_the_latest_invoice_is_marked_paid(): void
    {
        $this->givenOrderAwaitingOfflinePayment();
        $this->givenEventWithoutOrganizerConfiguration();
        $this->orderRepository->shouldReceive('updateFromArray')->once();
        $this->attendeeRepository->shouldReceive('updateWhere')->once();
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();
        $this->sendOrderDetailsService->shouldReceive('sendCustomerOrderSummary')->once();

        $this->invoiceRepository->shouldReceive('findLatestInvoiceForOrder')
            ->once()
            ->with(self::ORDER_ID)
            ->andReturn((new InvoiceDomainObject)->setId(77));
        $this->invoiceRepository->shouldReceive('updateFromArray')
            ->once()
            ->with(77, ['status' => InvoiceStatus::PAID->name]);

        $this->service->markOrderAsPaid(self::ORDER_ID, self::EVENT_ID);
    }

    public function test_an_order_that_is_not_awaiting_offline_payment_cannot_be_marked_as_paid(): void
    {
        $this->givenOrderAwaitingOfflinePayment(status: OrderStatus::COMPLETED);
        $this->givenEventWithoutOrganizerConfiguration(expectSecondLoad: false);

        $this->orderRepository->shouldNotReceive('updateFromArray');
        $this->attendeeRepository->shouldNotReceive('updateWhere');
        $this->occurrenceStatusValidator->shouldNotReceive('assertOrderOccurrencesArePurchasable');

        $this->expectException(ResourceConflictException::class);

        try {
            $this->service->markOrderAsPaid(self::ORDER_ID, self::EVENT_ID);
        } finally {
            Event::assertNotDispatched(OrderStatusChangedEvent::class);
        }
    }

    public function test_an_order_for_a_cancelled_occurrence_cannot_be_marked_as_paid(): void
    {
        $this->givenOrderAwaitingOfflinePayment(occurrencePurchasable: false);
        $this->givenEventWithoutOrganizerConfiguration(expectSecondLoad: false);

        $this->orderRepository->shouldNotReceive('updateFromArray');
        $this->attendeeRepository->shouldNotReceive('updateWhere');

        $this->expectException(ResourceConflictException::class);

        try {
            $this->service->markOrderAsPaid(self::ORDER_ID, self::EVENT_ID);
        } finally {
            Event::assertNotDispatched(OrderStatusChangedEvent::class);
        }
    }

    private function givenOrderAwaitingOfflinePayment(
        OrderStatus $status = OrderStatus::AWAITING_OFFLINE_PAYMENT,
        ?int $affiliateId = null,
        float $totalGross = 50.0,
        bool $occurrencePurchasable = true,
    ): void {
        $pending = (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setEventId(self::EVENT_ID)
            ->setStatus($status->name)
            ->setTotalGross($totalGross)
            ->setCurrency('USD');

        $this->orderRepository->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                OrderDomainObjectAbstract::ID => self::ORDER_ID,
                OrderDomainObjectAbstract::EVENT_ID => self::EVENT_ID,
            ])
            ->andReturn($pending);

        if ($status !== OrderStatus::AWAITING_OFFLINE_PAYMENT) {
            return;
        }

        if (! $occurrencePurchasable) {
            $this->occurrenceStatusValidator->shouldReceive('assertOrderOccurrencesArePurchasable')
                ->once()
                ->andThrow(new ResourceConflictException('occurrence cancelled'));

            return;
        }

        $this->occurrenceStatusValidator->shouldReceive('assertOrderOccurrencesArePurchasable')->once();

        $completed = (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setEventId(self::EVENT_ID)
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setTotalGross($totalGross)
            ->setCurrency('USD')
            ->setAffiliateId($affiliateId);

        $this->orderRepository->shouldReceive('findById')
            ->once()
            ->with(self::ORDER_ID)
            ->andReturn($completed);
    }

    private function givenEventWithoutOrganizerConfiguration(bool $expectSecondLoad = true): void
    {
        $event = (new EventDomainObject)
            ->setId(self::EVENT_ID)
            ->setOrganizer(new OrganizerDomainObject)
            ->setEventSettings(new EventSettingDomainObject);

        $this->eventRepository->shouldReceive('findById')
            ->times($expectSecondLoad ? 2 : 1)
            ->with(self::EVENT_ID)
            ->andReturn($event);
    }
}
