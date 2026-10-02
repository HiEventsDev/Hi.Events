<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\BoxOfficeSaleExpiredException;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderCompletionService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedOrderCompletionGuard;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OccurrenceStatusValidator;
use HiEvents\Services\Domain\Order\OfflineApplicationFeeRecordService;
use HiEvents\Services\Domain\Order\OrderManagementService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BoxOfficeOrderCompletionServiceTest extends TestCase
{
    private const ORDER_ID = 41;

    private MockInterface|OrderRepositoryInterface $orderRepository;

    private MockInterface|OrderItemRepositoryInterface $orderItemRepository;

    private MockInterface|AttendeeRepositoryInterface $attendeeRepository;

    private MockInterface|OrderManagementService $orderManagementService;

    private MockInterface|ProductQuantityUpdateService $productQuantityUpdateService;

    private MockInterface|OfflineApplicationFeeRecordService $offlineApplicationFeeRecordService;

    private BoxOfficeOrderCompletionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->orderManagementService = Mockery::mock(OrderManagementService::class);
        $this->productQuantityUpdateService = Mockery::mock(ProductQuantityUpdateService::class);
        $this->offlineApplicationFeeRecordService = Mockery::mock(OfflineApplicationFeeRecordService::class);

        $occurrenceStatusValidator = Mockery::mock(OccurrenceStatusValidator::class);
        $occurrenceStatusValidator->shouldReceive('assertOrderOccurrencesArePurchasable');

        $domainEventDispatcher = Mockery::mock(DomainEventDispatcherService::class);
        $domainEventDispatcher->shouldReceive('dispatch');

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());
        $databaseManager->shouldReceive('statement');

        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->service = new BoxOfficeOrderCompletionService(
            databaseManager: $databaseManager,
            orderRepository: $this->orderRepository,
            orderItemRepository: $this->orderItemRepository,
            attendeeRepository: $this->attendeeRepository,
            orderManagementService: $this->orderManagementService,
            occurrenceStatusValidator: $occurrenceStatusValidator,
            productQuantityUpdateService: $this->productQuantityUpdateService,
            domainEventDispatcherService: $domainEventDispatcher,
            offlineApplicationFeeRecordService: $this->offlineApplicationFeeRecordService,
            seatedOrderCompletionGuard: Mockery::mock(SeatedOrderCompletionGuard::class)->shouldIgnoreMissing(),
            transactionLockService: new TransactionLockService($databaseManager),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cash_tender_records_change_and_owed_fee(): void
    {
        $order = $this->reservedOrder(25.0);
        $this->orderRepository->shouldReceive('findById')->with(self::ORDER_ID)->andReturn($order);
        $this->expectCompletionSideEffects();

        $this->orderRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $id === self::ORDER_ID
                && $attributes[OrderDomainObjectAbstract::STATUS] === OrderStatus::COMPLETED->name
                && $attributes[OrderDomainObjectAbstract::PAYMENT_STATUS] === OrderPaymentStatus::PAYMENT_RECEIVED->name
                && $attributes[OrderDomainObjectAbstract::PAYMENT_PROVIDER] === PaymentProviders::OFFLINE->value
                && $attributes[OrderDomainObjectAbstract::BOX_OFFICE_TENDER] === BoxOfficeTender::CASH->value
                && $attributes[OrderDomainObjectAbstract::BOX_OFFICE_AMOUNT_TENDERED] === 30.0
                && $attributes[OrderDomainObjectAbstract::BOX_OFFICE_CHANGE_DUE] === 5.0
                && Carbon::parse($attributes[OrderDomainObjectAbstract::BOX_OFFICE_COMPLETED_AT])->isSameMinute(Carbon::now()))
            ->andReturn($order);

        $this->offlineApplicationFeeRecordService->shouldReceive('record')->once()->with($order);

        $result = $this->service->completeOffline($order, new EventDomainObject, BoxOfficeTender::CASH, 30.0, null, null);

        $this->assertSame($order, $result);
    }

    public function test_comp_tender_is_persisted_as_comp_with_zeroed_totals(): void
    {
        $order = $this->reservedOrder(25.0);
        $zeroed = $this->reservedOrder(0.0);
        $this->orderRepository->shouldReceive('findById')->with(self::ORDER_ID)->andReturn($order);
        $this->expectCompletionSideEffects();

        $this->orderItemRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $id === 7
                && $attributes['price'] === 0
                && $attributes['price_before_discount'] === 25.0
                && $attributes['total_gross'] === 0);
        $this->orderItemRepository->shouldReceive('findWhere')->once()->andReturn(collect());
        $this->orderManagementService->shouldReceive('updateOrderTotals')->once()->andReturn($zeroed);

        $this->orderRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $attributes[OrderDomainObjectAbstract::BOX_OFFICE_TENDER] === BoxOfficeTender::COMP->value
                && $attributes[OrderDomainObjectAbstract::PAYMENT_STATUS] === OrderPaymentStatus::NO_PAYMENT_REQUIRED->name
                && $attributes[OrderDomainObjectAbstract::PAYMENT_PROVIDER] === null)
            ->andReturn($zeroed);

        $this->offlineApplicationFeeRecordService->shouldReceive('record')->once();

        $result = $this->service->completeOffline($order, new EventDomainObject, BoxOfficeTender::COMP, null, null, null);

        $this->assertSame($order, $result);
    }

    public function test_zero_total_order_completes_as_free_without_a_fee_record(): void
    {
        $order = $this->reservedOrder(0.0);
        $this->orderRepository->shouldReceive('findById')->with(self::ORDER_ID)->andReturn($order);
        $this->expectCompletionSideEffects();

        $this->orderRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $attributes[OrderDomainObjectAbstract::BOX_OFFICE_TENDER] === BoxOfficeTender::FREE->value
                && $attributes[OrderDomainObjectAbstract::PAYMENT_STATUS] === OrderPaymentStatus::NO_PAYMENT_REQUIRED->name)
            ->andReturn($order);

        $this->offlineApplicationFeeRecordService->shouldNotReceive('record');

        $result = $this->service->completeOffline($order, new EventDomainObject, BoxOfficeTender::CASH, 0.0, null, null);

        $this->assertSame($order, $result);
    }

    public function test_cash_short_of_the_total_is_rejected(): void
    {
        $order = $this->reservedOrder(25.0);
        $this->orderRepository->shouldReceive('findById')->with(self::ORDER_ID)->andReturn($order);
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->expectException(ValidationException::class);

        $this->service->completeOffline($order, new EventDomainObject, BoxOfficeTender::CASH, 20.0, null, null);
    }

    public function test_completed_order_cannot_be_tendered_again(): void
    {
        $order = $this->reservedOrder(25.0)->setStatus(OrderStatus::COMPLETED->name);
        $this->orderRepository->shouldReceive('findById')->with(self::ORDER_ID)->andReturn($order);
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->expectException(ResourceConflictException::class);

        $this->service->completeOffline($order, new EventDomainObject, BoxOfficeTender::CASH, 30.0, null, null);
    }

    public function test_a_card_payment_started_after_the_release_blocks_an_offline_tender(): void
    {
        $order = $this->reservedOrder(25.0)->setStripePayment((new StripePaymentDomainObject)->setPaymentIntentId('pi_new'));
        $this->orderRepository->shouldReceive('findById')->with(self::ORDER_ID)->andReturn($order);
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->expectException(ResourceConflictException::class);

        $this->service->completeOffline($order, new EventDomainObject, BoxOfficeTender::COMP, null, null, 'pi_released');
    }

    public function test_a_card_payment_started_when_none_was_released_blocks_an_offline_tender(): void
    {
        $order = $this->reservedOrder(25.0)->setStripePayment((new StripePaymentDomainObject)->setPaymentIntentId('pi_new'));
        $this->orderRepository->shouldReceive('findById')->with(self::ORDER_ID)->andReturn($order);
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->expectException(ResourceConflictException::class);

        $this->service->completeOffline($order, new EventDomainObject, BoxOfficeTender::CASH, 30.0, null, null);
    }

    public function test_the_released_card_payment_does_not_block_an_offline_tender(): void
    {
        $order = $this->reservedOrder(25.0)->setStripePayment((new StripePaymentDomainObject)->setPaymentIntentId('pi_released'));
        $this->orderRepository->shouldReceive('findById')->with(self::ORDER_ID)->andReturn($order);
        $this->expectCompletionSideEffects();
        $this->orderRepository->shouldReceive('updateFromArray')->once()->andReturn($order);
        $this->offlineApplicationFeeRecordService->shouldReceive('record')->once();

        $result = $this->service->completeOffline($order, new EventDomainObject, BoxOfficeTender::CASH, 30.0, null, 'pi_released');

        $this->assertSame($order, $result);
    }

    public function test_an_expired_sale_reports_the_sale_expired_code(): void
    {
        $order = $this->reservedOrder(25.0)->setReservedUntil(Carbon::now()->subMinute()->toDateTimeString());
        $this->orderRepository->shouldReceive('findById')->with(self::ORDER_ID)->andReturn($order);
        $this->orderRepository->shouldNotReceive('updateFromArray');

        $this->expectException(BoxOfficeSaleExpiredException::class);

        $this->service->completeOffline($order, new EventDomainObject, BoxOfficeTender::CASH, 30.0, null, null);
    }

    private function reservedOrder(float $totalGross): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setShortId('o_door')
            ->setStatus(OrderStatus::RESERVED->name)
            ->setCurrency('USD')
            ->setTotalGross($totalGross)
            ->setReservedUntil(Carbon::now()->addMinutes(20)->toDateTimeString())
            ->setOrderItems(collect([
                (new OrderItemDomainObject)->setId(7)->setPrice(25.0)->setQuantity(1),
            ]));
    }

    private function expectCompletionSideEffects(): void
    {
        $this->attendeeRepository->shouldReceive('updateWhere')->once();
        $this->productQuantityUpdateService->shouldReceive('updateQuantitiesFromOrder')->once();
    }
}
