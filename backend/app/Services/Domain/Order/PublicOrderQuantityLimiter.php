<?php

namespace HiEvents\Services\Domain\Order;

use Closure;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\OrderReservationLimitExceededException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;

class PublicOrderQuantityLimiter
{
    private const TRACKING_SECONDS = 86400;

    private const LOCK_SECONDS = 10;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * @param  Closure(): OrderDomainObject  $createOrder
     *
     * @throws OrderReservationLimitExceededException
     */
    public function reserve(int $eventId, ?string $clientIp, int $quantity, Closure $createOrder, string $sessionId): OrderDomainObject
    {
        $budget = (int) $this->config->get('app.public_order_reserved_quantity_per_ip');

        if ($budget <= 0 || $clientIp === null) {
            return $createOrder();
        }

        $key = "public-order-reservations|$eventId|$clientIp";

        return $this->cache->lock("$key|lock", self::LOCK_SECONDS)->block(
            self::LOCK_SECONDS,
            function () use ($key, $budget, $quantity, $createOrder, $sessionId) {
                $liveOrders = $this->liveReservations($this->cache->get($key, []));
                $liveQuantity = $liveOrders
                    ->reject(fn (OrderDomainObject $order) => $order->getSessionId() === $sessionId)
                    ->sum(fn (OrderDomainObject $order) => $order->getOrderItems()
                        ?->sum(fn (OrderItemDomainObject $item) => $item->getQuantity()) ?? 0);

                if ($liveQuantity > 0 && $liveQuantity + $quantity > $budget) {
                    throw new OrderReservationLimitExceededException(
                        __('You already have tickets reserved for this event. Complete or cancel that order before reserving more.')
                    );
                }

                $order = $createOrder();

                $this->cache->put(
                    $key,
                    [...$liveOrders->map(fn (OrderDomainObject $live) => $live->getId())->all(), $order->getId()],
                    self::TRACKING_SECONDS,
                );

                return $order;
            },
        );
    }

    /**
     * @param  array<int>  $orderIds
     * @return Collection<int, OrderDomainObject>
     */
    private function liveReservations(array $orderIds): Collection
    {
        if ($orderIds === []) {
            return collect();
        }

        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findWhereIn(
                field: OrderDomainObjectAbstract::ID,
                values: $orderIds,
                additionalWhere: [
                    OrderDomainObjectAbstract::STATUS => OrderStatus::RESERVED->name,
                    [OrderDomainObjectAbstract::RESERVED_UNTIL, '>', now()->toDateTimeString()],
                ],
            );
    }
}
