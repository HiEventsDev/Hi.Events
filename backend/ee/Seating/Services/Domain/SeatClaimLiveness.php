<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\DomainObjects\Status\OrderStatus;

final class SeatClaimLiveness
{
    public const ORDER_STATUSES_ALWAYS_LIVE = [OrderStatus::COMPLETED, OrderStatus::AWAITING_OFFLINE_PAYMENT];

    public const ORDER_STATUSES_LIVE_UNTIL_EXPIRY = [OrderStatus::RESERVED];

    public const ORDER_STATUSES_DEAD = [OrderStatus::CANCELLED, OrderStatus::ABANDONED];

    public static function orderKeepsSeatsIndefinitely(string $orderStatus): bool
    {
        return in_array(OrderStatus::fromName($orderStatus), self::ORDER_STATUSES_ALWAYS_LIVE, true);
    }
}
