<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimLiveness;
use Tests\TestCase;

class SeatClaimLivenessTest extends TestCase
{
    public function test_every_order_status_is_classified_exactly_once(): void
    {
        $classified = [
            ...SeatClaimLiveness::ORDER_STATUSES_ALWAYS_LIVE,
            ...SeatClaimLiveness::ORDER_STATUSES_LIVE_UNTIL_EXPIRY,
            ...SeatClaimLiveness::ORDER_STATUSES_DEAD,
        ];

        $this->assertEqualsCanonicalizing(
            array_map(fn (OrderStatus $status) => $status->name, OrderStatus::cases()),
            array_map(fn (OrderStatus $status) => $status->name, $classified),
            'A new OrderStatus must be classified in SeatClaimLiveness so seat claims know whether it holds a seat',
        );
    }

    public function test_only_completed_and_offline_pending_orders_keep_seats_indefinitely(): void
    {
        $this->assertTrue(SeatClaimLiveness::orderKeepsSeatsIndefinitely(OrderStatus::COMPLETED->name));
        $this->assertTrue(SeatClaimLiveness::orderKeepsSeatsIndefinitely(OrderStatus::AWAITING_OFFLINE_PAYMENT->name));
        $this->assertFalse(SeatClaimLiveness::orderKeepsSeatsIndefinitely(OrderStatus::RESERVED->name));
        $this->assertFalse(SeatClaimLiveness::orderKeepsSeatsIndefinitely(OrderStatus::CANCELLED->name));
    }
}
