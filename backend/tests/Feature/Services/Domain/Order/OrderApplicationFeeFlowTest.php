<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderApplicationFeeDomainObjectAbstract;
use HiEvents\DomainObjects\OrderApplicationFeeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderApplicationFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderApplicationFeeFlowTest extends TestCase
{
    use DatabaseTransactions;

    private OrderApplicationFeeCalculationService $calculationService;

    private OrderApplicationFeeService $feeService;

    private OrderApplicationFeeRepositoryInterface $feeRepository;

    private OrderRepositoryInterface $orderRepository;

    private EventRepositoryInterface $eventRepository;

    private int $accountId;

    private int $eventId;

    private int $organizerConfigurationId;

    private int $productId;

    private int $productPriceId;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.saas_mode_enabled', true);

        $this->calculationService = $this->app->make(OrderApplicationFeeCalculationService::class);
        $this->feeService = $this->app->make(OrderApplicationFeeService::class);
        $this->feeRepository = $this->app->make(OrderApplicationFeeRepositoryInterface::class);
        $this->orderRepository = $this->app->make(OrderRepositoryInterface::class);
        $this->eventRepository = $this->app->make(EventRepositoryInterface::class);

        $now = now()->toDateTimeString();

        $user = User::factory()->withAccount()->create();
        $this->accountId = $user->accounts()->first()->id;

        $this->organizerConfigurationId = DB::table('organizer_configurations')->insertGetId([
            'name' => 'Test Fee Config',
            'is_system_default' => false,
            'application_fees' => json_encode([
                'fixed' => 1.00,
                'percentage' => 10,
                'currency' => 'USD',
            ], JSON_THROW_ON_ERROR),
            'bypass_application_fees' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Test Organizer',
            'email' => 'organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'organizer_configuration_id' => $this->organizerConfigurationId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventId = DB::table('events')->insertGetId([
            'title' => 'Test Event',
            'account_id' => $this->accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'test_evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'title' => 'Test Product',
            'event_id' => $this->eventId,
            'order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->productPriceId = DB::table('product_prices')->insertGetId([
            'product_id' => $this->productId,
            'price' => 25.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_application_fee_recorded_exactly_once_on_completion(): void
    {
        $orderId = $this->insertCompletedOrder(price: 25.00, quantity: 2, totalGross: 50.00);

        $this->recordApplicationFeeAsMarkOrderAsPaidWould($orderId);

        $fees = $this->feeRepository->findWhere([
            OrderApplicationFeeDomainObjectAbstract::ORDER_ID => $orderId,
        ]);

        $this->assertCount(1, $fees, 'Exactly one application fee row must be recorded on completion');

        /** @var OrderApplicationFeeDomainObject $fee */
        $fee = $fees->first();

        $this->assertEqualsWithDelta(7.00, $fee->getAmount(), 0.001, 'Recorded fee amount must be 7.00 (fixed 1.00 x 2 + 10% of 50.00)');
        $this->assertSame('USD', $fee->getCurrency());
        $this->assertSame(OrderApplicationFeeStatus::AWAITING_PAYMENT->value, $fee->getStatus());
        $this->assertSame(PaymentProviders::OFFLINE->value, $fee->getPaymentMethod());

        $this->assertSame(
            1,
            $this->liveFeeRowCount($orderId),
            'Exactly one non-deleted order_application_fees row must exist for the order',
        );
    }

    public function test_free_order_records_zero_fee(): void
    {
        $orderId = $this->insertCompletedOrder(price: 0.00, quantity: 3, totalGross: 0.00);

        $this->recordApplicationFeeAsMarkOrderAsPaidWould($orderId);

        $fees = $this->feeRepository->findWhere([
            OrderApplicationFeeDomainObjectAbstract::ORDER_ID => $orderId,
        ]);

        $this->assertCount(1, $fees);

        /** @var OrderApplicationFeeDomainObject $fee */
        $fee = $fees->first();

        $this->assertEqualsWithDelta(0.00, $fee->getAmount(), 0.001);
        $this->assertSame('USD', $fee->getCurrency());
    }

    public function test_re_recording_after_completion_does_not_create_a_second_fee_row_when_guarded_by_status(): void
    {
        $orderId = $this->insertCompletedOrder(price: 25.00, quantity: 2, totalGross: 50.00);

        $this->recordApplicationFeeAsMarkOrderAsPaidWould($orderId);

        $this->recordApplicationFeeIfStillAwaitingOfflinePayment($orderId);

        $this->assertSame(
            1,
            $this->liveFeeRowCount($orderId),
            'A second completion attempt must not create a duplicate fee row',
        );
    }

    public function test_refund_does_not_create_a_duplicate_or_negative_fee_row(): void
    {
        $orderId = $this->insertCompletedOrder(price: 25.00, quantity: 2, totalGross: 50.00);

        $this->recordApplicationFeeAsMarkOrderAsPaidWould($orderId);

        $this->orderRepository->updateFromArray($orderId, [
            'status' => OrderStatus::CANCELLED->name,
            'refund_status' => 'REFUNDED',
            'total_refunded' => 50.00,
        ]);

        $this->assertSame(
            1,
            $this->liveFeeRowCount($orderId),
            'A refund must not add another application fee row',
        );

        $remaining = $this->feeRepository->findWhere([
            OrderApplicationFeeDomainObjectAbstract::ORDER_ID => $orderId,
        ]);

        /** @var OrderApplicationFeeDomainObject $fee */
        $fee = $remaining->first();
        $this->assertGreaterThanOrEqual(0.0, $fee->getAmount(), 'Fee amount must never go negative after a refund');
        $this->assertEqualsWithDelta(7.00, $fee->getAmount(), 0.001);
    }

    private function recordApplicationFeeAsMarkOrderAsPaidWould(int $orderId): void
    {
        $order = $this->loadOrderWithItems($orderId);
        $config = $this->loadOrganizerConfigurationForOrder($order);

        $this->assertInstanceOf(
            OrganizerConfigurationDomainObject::class,
            $config,
            'Organizer configuration must hydrate through the event->organizer eager-load chain',
        );

        $this->feeService->createOrderApplicationFee(
            orderId: $order->getId(),
            applicationFeeAmountMinorUnit: $this->calculationService->calculateApplicationFee(
                configuration: $config,
                order: $order,
            )?->netApplicationFee?->toMinorUnit() ?? 0,
            orderApplicationFeeStatus: OrderApplicationFeeStatus::AWAITING_PAYMENT,
            paymentMethod: PaymentProviders::OFFLINE,
            currency: $order->getCurrency(),
        );
    }

    private function recordApplicationFeeIfStillAwaitingOfflinePayment(int $orderId): void
    {
        $order = $this->loadOrderWithItems($orderId);

        if ($order->getStatus() !== OrderStatus::AWAITING_OFFLINE_PAYMENT->name) {
            return;
        }

        $this->recordApplicationFeeAsMarkOrderAsPaidWould($orderId);
    }

    private function loadOrderWithItems(int $orderId): OrderDomainObject
    {
        /** @var OrderDomainObject $order */
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($orderId);

        return $order;
    }

    private function loadOrganizerConfigurationForOrder(OrderDomainObject $order): ?OrganizerConfigurationDomainObject
    {
        /** @var EventDomainObject $event */
        $event = $this->eventRepository
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: OrganizerConfigurationDomainObject::class,
                        name: 'organizer_configuration',
                    ),
                ],
                name: 'organizer',
            ))
            ->findById($order->getEventId());

        return $event->getOrganizer()?->getOrganizerConfiguration();
    }

    private function insertCompletedOrder(float $price, int $quantity, float $totalGross): int
    {
        $now = now();

        $orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => OrderStatus::COMPLETED->name,
            'total_gross' => $totalGross,
            'total_before_additions' => $totalGross,
            'public_id' => 'pub_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->productPriceId,
            'quantity' => $quantity,
            'price' => $price,
            'total_before_additions' => $price * $quantity,
        ]);

        return $orderId;
    }

    private function liveFeeRowCount(int $orderId): int
    {
        return DB::table('order_application_fees')
            ->where('order_id', $orderId)
            ->whereNull('deleted_at')
            ->count();
    }
}
