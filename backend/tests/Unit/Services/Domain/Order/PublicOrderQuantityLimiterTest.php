<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\OrderReservationLimitExceededException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\PublicOrderQuantityLimiter;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as ConfigRepository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PublicOrderQuantityLimiterTest extends TestCase
{
    private OrderRepositoryInterface|MockInterface $orderRepository;

    /** @var array<int, OrderDomainObject> */
    private array $orders = [];

    private int $nextOrderId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->orderRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->orderRepository->shouldReceive('findWhereIn')->andReturnUsing(
            fn (string $field, array $ids) => collect($ids)
                ->map(fn (int $id) => $this->orders[$id] ?? null)
                ->filter(fn (?OrderDomainObject $order) => $order !== null
                    && $order->getStatus() === OrderStatus::RESERVED->name
                    && ! $order->isReservedOrderExpired())
                ->values(),
        );
    }

    public function test_nothing_is_limited_until_a_budget_is_configured(): void
    {
        $limiter = $this->limiter(null);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $limiter->reserve(1, '203.0.113.5', 100, fn () => $this->createOrder(100), $this->newSession());
        }

        $this->orderRepository->shouldNotHaveReceived('findWhereIn');
    }

    public function test_orders_within_the_budget_are_allowed(): void
    {
        $limiter = $this->limiter(10);

        $limiter->reserve(1, '203.0.113.5', 4, fn () => $this->createOrder(4), $this->newSession());
        $order = $limiter->reserve(1, '203.0.113.5', 6, fn () => $this->createOrder(6), $this->newSession());

        $this->assertSame(2, $order->getId());
    }

    public function test_an_order_that_takes_live_reservations_past_the_budget_is_refused(): void
    {
        $limiter = $this->limiter(10);
        $limiter->reserve(1, '203.0.113.5', 8, fn () => $this->createOrder(8), $this->newSession());

        $this->expectException(OrderReservationLimitExceededException::class);
        $limiter->reserve(1, '203.0.113.5', 3, fn () => $this->fail('The order should not be created'), $this->newSession());
    }

    public function test_a_single_large_order_is_not_refused_on_its_own(): void
    {
        $limiter = $this->limiter(10);

        $order = $limiter->reserve(1, '203.0.113.5', 25, fn () => $this->createOrder(25), $this->newSession());

        $this->assertSame(1, $order->getId());
    }

    public function test_reservations_that_were_completed_expired_or_replaced_no_longer_count(): void
    {
        $limiter = $this->limiter(10);
        $limiter->reserve(1, '203.0.113.5', 8, fn () => $this->createOrder(8), $this->newSession())->setStatus(OrderStatus::COMPLETED->name);
        $limiter->reserve(1, '203.0.113.5', 8, fn () => $this->createOrder(8), $this->newSession())->setReservedUntil(now()->subMinute()->toDateTimeString());
        unset($this->orders[$limiter->reserve(1, '203.0.113.5', 8, fn () => $this->createOrder(8), $this->newSession())->getId()]);

        $order = $limiter->reserve(1, '203.0.113.5', 9, fn () => $this->createOrder(9), $this->newSession());

        $this->assertSame(4, $order->getId());
    }

    public function test_the_buyers_own_reservation_does_not_count_because_the_new_order_replaces_it(): void
    {
        $limiter = $this->limiter(10);
        $limiter->reserve(1, '203.0.113.5', 8, fn () => $this->createOrder(8, 'buyer-session'), 'buyer-session');

        $order = $limiter->reserve(1, '203.0.113.5', 9, fn () => $this->createOrder(9, 'buyer-session'), 'buyer-session');

        $this->assertSame(2, $order->getId());
    }

    public function test_a_failed_order_does_not_use_up_the_budget(): void
    {
        $limiter = $this->limiter(10);

        try {
            $limiter->reserve(1, '203.0.113.5', 9, fn () => throw new \RuntimeException('Sold out'), $this->newSession());
        } catch (\RuntimeException) {
        }
        $limiter->reserve(1, '203.0.113.5', 9, fn () => $this->createOrder(9), $this->newSession());
        $order = $limiter->reserve(1, '203.0.113.5', 1, fn () => $this->createOrder(1), $this->newSession());

        $this->assertSame(2, $order->getId());
    }

    public function test_budgets_are_kept_per_event_and_per_client(): void
    {
        $limiter = $this->limiter(10);

        $limiter->reserve(1, '203.0.113.5', 10, fn () => $this->createOrder(10), $this->newSession());
        $limiter->reserve(2, '203.0.113.5', 10, fn () => $this->createOrder(10), $this->newSession());
        $order = $limiter->reserve(1, '198.51.100.7', 10, fn () => $this->createOrder(10), $this->newSession());

        $this->assertSame(3, $order->getId());
    }

    public function test_a_request_without_a_client_address_is_not_limited(): void
    {
        $limiter = $this->limiter(10);
        $limiter->reserve(1, null, 10, fn () => $this->createOrder(10), $this->newSession());

        $order = $limiter->reserve(1, null, 10, fn () => $this->createOrder(10), $this->newSession());

        $this->assertSame(2, $order->getId());
    }

    private function limiter(?int $budget): PublicOrderQuantityLimiter
    {
        return new PublicOrderQuantityLimiter(
            new Repository(new ArrayStore),
            $this->orderRepository,
            new ConfigRepository(['app' => ['public_order_reserved_quantity_per_ip' => $budget]]),
        );
    }

    private function newSession(): string
    {
        return 'session-'.uniqid('', true);
    }

    private function createOrder(int $quantity, ?string $sessionId = null): OrderDomainObject
    {
        $order = (new OrderDomainObject)
            ->setId($this->nextOrderId++)
            ->setSessionId($sessionId)
            ->setStatus(OrderStatus::RESERVED->name)
            ->setReservedUntil(now()->addMinutes(15)->toDateTimeString())
            ->setOrderItems(collect([(new OrderItemDomainObject)->setQuantity($quantity)]));

        return $this->orders[$order->getId()] = $order;
    }
}
