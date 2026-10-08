<?php

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatSelectionDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedOrderCompletionGuard;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class SeatedOrderCompletionGuardTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const SEAT = 'e6.0.0';

    private const ZONE = 'z2';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $eventSeatMapId;

    private array $layout;

    /** @var string[] */
    private array $executedStatements = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null);

        $this->layout = $this->seatMapFixture('club');
        $this->layout['areas'][0]['elements'][1]['capacity'] = 2;
        $this->eventSeatMapId = $this->insertEventSeatMap($this->layout, ['b_premium' => [$this->productId]]);

        DB::listen(function ($query): void {
            $this->executedStatements[] = $query->sql;
        });
    }

    public function test_an_expired_order_with_all_its_claims_intact_can_be_completed(): void
    {
        $orderId = $this->expiredOrderWithSeats([self::SEAT, self::ZONE]);

        $this->assertTrue($this->canStillBeCompleted($orderId));
    }

    public function test_an_order_whose_seat_was_taken_by_another_buyer_cannot(): void
    {
        $orderId = $this->expiredOrderWithSeats([self::SEAT]);
        $this->claim($this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15)), [self::SEAT]);

        $this->assertFalse($this->canStillBeCompleted($orderId));
    }

    public function test_an_order_whose_zone_has_since_filled_cannot(): void
    {
        $orderId = $this->expiredOrderWithSeats([self::ZONE]);
        $this->claim($this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15)), [self::ZONE, self::ZONE]);

        $this->assertFalse($this->canStillBeCompleted($orderId));
    }

    public function test_an_order_whose_seat_was_removed_from_the_map_cannot(): void
    {
        $orderId = $this->expiredOrderWithSeats([self::SEAT]);

        $layout = $this->layout;
        $layout['areas'][0]['elements'][5]['seats'] = array_values(array_filter(
            $layout['areas'][0]['elements'][5]['seats'],
            fn (array $seat) => $seat['uid'] !== self::SEAT,
        ));
        $this->updateEventSeatMap($this->eventSeatMapId, ['layout' => json_encode($layout)]);

        $this->assertFalse($this->canStillBeCompleted($orderId));
    }

    public function test_an_order_whose_claims_were_purged_cannot(): void
    {
        $orderId = $this->expiredOrderWithSeats([self::SEAT]);
        DB::table('seat_claims')->where('order_id', $orderId)->delete();

        $this->assertFalse($this->canStillBeCompleted($orderId));
    }

    public function test_an_order_without_seats_is_never_rescued(): void
    {
        $unseatedProductId = $this->insertProduct();
        $orderId = $this->insertOrder(OrderStatus::RESERVED->name, now()->subMinutes(2));
        $this->insertOrderItem($orderId, $unseatedProductId, $this->insertPrice($unseatedProductId, 10), $this->occurrenceId, 1);

        $this->assertFalse($this->canStillBeCompleted($orderId));
    }

    public function test_an_order_without_seats_never_takes_the_event_lock(): void
    {
        $unseatedProductId = $this->insertProduct();
        $orderId = $this->insertOrder(OrderStatus::RESERVED->name, now()->subMinutes(2));
        $this->insertOrderItem($orderId, $unseatedProductId, $this->insertPrice($unseatedProductId, 10), $this->occurrenceId, 1);

        $this->assertFalse($this->canStillBeCompleted($orderId));
        $this->assertSame([], $this->advisoryLockStatements());
    }

    public function test_a_seated_order_takes_the_event_lock(): void
    {
        $orderId = $this->expiredOrderWithSeats([self::SEAT]);

        $this->assertTrue($this->canStillBeCompleted($orderId));
        $this->assertNotSame([], $this->advisoryLockStatements());
    }

    public function test_a_mixed_order_is_not_rescued_once_its_unseated_item_has_sold_out(): void
    {
        $parkingProductId = $this->insertProduct();
        $parkingPriceId = $this->insertPrice($parkingProductId, 1, 'EVENT');
        $orderId = $this->expiredOrderWithSeats([self::SEAT]);
        $this->insertOrderItem($orderId, $parkingProductId, $parkingPriceId, $this->occurrenceId, 1);

        $this->assertTrue($this->canStillBeCompleted($orderId));

        DB::table('product_prices')->where('id', $parkingPriceId)->update(['quantity_sold' => 1]);

        $this->assertFalse($this->canStillBeCompleted($orderId));
    }

    public function test_a_seated_order_is_not_rescued_once_its_shared_pool_has_filled(): void
    {
        $orderId = $this->expiredOrderWithSeats([self::SEAT]);
        $capacityAssignmentId = DB::table('capacity_assignments')->insertGetId([
            'event_id' => $this->eventId,
            'name' => 'Shared pool',
            'capacity' => 10,
            'used_capacity' => 9,
            'applies_to' => 'PRODUCTS',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_capacity_assignments')->insert([
            'product_id' => $this->productId,
            'capacity_assignment_id' => $capacityAssignmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->canStillBeCompleted($orderId));

        DB::table('capacity_assignments')->where('id', $capacityAssignmentId)->update(['used_capacity' => 10]);

        $this->assertFalse($this->canStillBeCompleted($orderId));
    }

    public function test_a_shared_pool_is_checked_against_every_item_of_the_order_together(): void
    {
        $parkingProductId = $this->insertProduct();
        $parkingPriceId = $this->insertPrice($parkingProductId, null);
        $orderId = $this->expiredOrderWithSeats([self::SEAT]);
        $this->insertOrderItem($orderId, $parkingProductId, $parkingPriceId, $this->occurrenceId, 2);
        $capacityAssignmentId = DB::table('capacity_assignments')->insertGetId([
            'event_id' => $this->eventId,
            'name' => 'Shared pool',
            'capacity' => 10,
            'used_capacity' => 7,
            'applies_to' => 'PRODUCTS',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_capacity_assignments')->insert([
            ['product_id' => $this->productId, 'capacity_assignment_id' => $capacityAssignmentId, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $parkingProductId, 'capacity_assignment_id' => $capacityAssignmentId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertTrue($this->canStillBeCompleted($orderId));

        DB::table('capacity_assignments')->where('id', $capacityAssignmentId)->update(['used_capacity' => 8]);

        $this->assertFalse($this->canStillBeCompleted($orderId));
    }

    public function test_the_date_capacity_is_checked_against_every_ticket_of_the_order_together(): void
    {
        $standingProductId = $this->insertProduct();
        $standingPriceId = $this->insertPrice($standingProductId, null);
        $orderId = $this->expiredOrderWithSeats([self::SEAT]);
        $this->insertOrderItem($orderId, $standingProductId, $standingPriceId, $this->occurrenceId, 2);
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['capacity' => 10, 'used_capacity' => 7]);

        $this->assertTrue($this->canStillBeCompleted($orderId));

        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['used_capacity' => 8]);

        $this->assertFalse($this->canStillBeCompleted($orderId));
    }

    public function test_a_seated_order_is_not_rescued_once_its_tier_has_sold_out(): void
    {
        DB::table('product_prices')->where('id', $this->priceId)->update([
            'initial_quantity_available' => 1,
            'quantity_applies_to' => 'EVENT',
        ]);
        $orderId = $this->expiredOrderWithSeats([self::SEAT]);

        $this->assertTrue($this->canStillBeCompleted($orderId));

        DB::table('product_prices')->where('id', $this->priceId)->update(['quantity_sold' => 1]);

        $this->assertFalse($this->canStillBeCompleted($orderId));
    }

    private function expiredOrderWithSeats(array $seatUids): int
    {
        $orderId = $this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15));
        $orderItemId = $this->insertOrderItem($orderId, $this->productId, $this->priceId, $this->occurrenceId, count($seatUids));
        $this->claim($orderId, $seatUids, $orderItemId);
        DB::table('orders')->where('id', $orderId)->update(['reserved_until' => now()->subMinutes(2)]);

        return $orderId;
    }

    private function claim(int $orderId, array $seatUids, ?int $orderItemId = null): void
    {
        app(SeatClaimService::class)->claimForOrder(
            (new OrderDomainObject)->setId($orderId)->setEventId($this->eventId),
            collect($seatUids)->map(fn (string $seatUid) => new SeatSelectionDTO(
                seat_uid: $seatUid,
                event_occurrence_id: $this->occurrenceId,
                product_id: $this->productId,
                product_price_id: $this->priceId,
                order_item_id: $orderItemId,
            )),
        );
    }

    /**
     * @return string[]
     */
    private function advisoryLockStatements(): array
    {
        return array_values(array_filter(
            $this->executedStatements,
            static fn (string $sql) => str_contains($sql, 'pg_advisory_xact_lock'),
        ));
    }

    private function canStillBeCompleted(int $orderId): bool
    {
        $order = app(OrderRepositoryInterface::class)
            ->loadRelation(OrderItemDomainObject::class)
            ->findFirstWhere([OrderDomainObjectAbstract::ID => $orderId]);

        return app(SeatedOrderCompletionGuard::class)->canRescueExpired($order);
    }
}
