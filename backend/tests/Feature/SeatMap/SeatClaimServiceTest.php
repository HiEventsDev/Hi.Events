<?php

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatSelectionDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatAvailabilityService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class SeatClaimServiceTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const SEAT = 'e2.0.0';

    private const OTHER_SEAT = 'e2.0.1';

    private const ZONE = 'z2';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private SeatClaimService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null);
        $this->service = app(SeatClaimService::class);
    }

    public function test_claim_stores_the_band_and_label_and_marks_the_seat_unavailable(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);

        $this->claim($this->reservedOrder(), [self::SEAT]);

        $this->assertDatabaseHas('seat_claims', [
            'event_occurrence_id' => $this->occurrenceId,
            'seat_uid' => self::SEAT,
            'band_key' => 'b_premium',
            'seat_label' => 'Stalls · A-1',
            'is_zone' => false,
        ]);

        $availability = app(SeatAvailabilityService::class)->forOccurrence($this->eventId, $this->occurrenceId);
        $this->assertSame([self::SEAT], $availability->unavailable_seat_uids);
        $this->assertSame(85, $availability->band_free['b_premium']);
    }

    public function test_a_seat_in_a_band_not_linked_to_the_ticket_is_refused_at_claim_time(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $order = $this->reservedOrder();

        try {
            DB::transaction(fn () => $this->claim($order, [self::SEAT, 'e3.0.0']));
            $this->fail('Expected the claim to fail');
        } catch (SeatsUnavailableException $exception) {
            $this->assertSame(['e3.0.0'], $exception->getSeatUids());
        }

        $this->assertSame(0, DB::table('seat_claims')->where('order_id', $order->getId())->count());
    }

    public function test_a_seat_held_by_another_order_is_reported_and_nothing_is_claimed(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), $this->everyBandLinked());
        $this->claim($this->reservedOrder(), [self::SEAT]);

        $second = $this->reservedOrder();
        try {
            DB::transaction(fn () => $this->claim($second, [self::OTHER_SEAT, self::SEAT]));
            $this->fail('Expected the claim to fail');
        } catch (SeatsUnavailableException $exception) {
            $this->assertSame([self::SEAT], $exception->getSeatUids());
        }

        $this->assertSame(0, DB::table('seat_claims')->where('order_id', $second->getId())->count());
    }

    public function test_claims_of_expired_deleted_cancelled_and_abandoned_orders_are_replaced(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), $this->everyBandLinked());

        $deadOrders = [
            $this->insertOrder(OrderStatus::RESERVED->name, now()->subMinute()),
            $this->insertOrder(OrderStatus::CANCELLED->name),
            $this->insertOrder(OrderStatus::ABANDONED->name),
            tap($this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15)), fn (int $id) => DB::table('orders')->where('id', $id)->update(['deleted_at' => now()])),
        ];

        foreach ($deadOrders as $deadOrderId) {
            DB::table('seat_claims')->delete();
            $this->insertClaimRow($deadOrderId, self::SEAT);

            $buyer = $this->reservedOrder();
            $this->claim($buyer, [self::SEAT]);

            $this->assertSame(
                [$buyer->getId()],
                DB::table('seat_claims')->where('seat_uid', self::SEAT)->pluck('order_id')->all(),
            );
        }
    }

    public function test_completed_and_offline_pending_orders_keep_their_seats(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), $this->everyBandLinked());

        foreach ([OrderStatus::COMPLETED, OrderStatus::AWAITING_OFFLINE_PAYMENT] as $status) {
            DB::table('seat_claims')->delete();
            $this->insertClaimRow($this->insertOrder($status->name, now()->subDay()), self::SEAT);

            try {
                $this->claim($this->reservedOrder(), [self::SEAT]);
                $this->fail("A {$status->name} order must keep its seat");
            } catch (SeatsUnavailableException $exception) {
                $this->assertSame([self::SEAT], $exception->getSeatUids());
            }
        }
    }

    public function test_the_same_seat_can_be_sold_on_another_date(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), $this->everyBandLinked());
        $otherOccurrenceId = $this->insertOccurrence(daysAhead: 2);

        $this->claim($this->reservedOrder(), [self::SEAT]);
        $this->claim($this->reservedOrder(), [self::SEAT], $otherOccurrenceId);

        $this->assertSame(2, DB::table('seat_claims')->where('seat_uid', self::SEAT)->count());
        $this->assertSame([], app(SeatAvailabilityService::class)
            ->forOccurrence($this->eventId, $this->insertOccurrence(daysAhead: 3))->unavailable_seat_uids);
    }

    public function test_zone_capacity_is_enforced_across_orders(): void
    {
        $layout = $this->seatMapFixture('club');
        $layout['areas'][0]['elements'][1]['capacity'] = 3;
        $this->insertEventSeatMap($layout, $this->everyBandLinked());

        $this->claim($this->reservedOrder(), [self::ZONE, self::ZONE]);

        try {
            $this->claim($this->reservedOrder(), [self::ZONE, self::ZONE]);
            $this->fail('Expected the zone to be full');
        } catch (SeatsUnavailableException $exception) {
            $this->assertSame([self::ZONE], $exception->getSeatUids());
        }

        $this->claim($this->reservedOrder(), [self::ZONE]);

        $availability = app(SeatAvailabilityService::class)->forOccurrence($this->eventId, $this->occurrenceId);
        $this->assertSame(0, $availability->zone_remaining[self::ZONE]);
        $this->assertSame([], $availability->unavailable_seat_uids);
    }

    public function test_long_dead_zone_claims_are_purged_but_recent_ones_are_kept_for_late_payment(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('club'), $this->everyBandLinked());
        $longDead = $this->insertOrder(OrderStatus::RESERVED->name, now()->subHours(25));
        $recentlyDead = $this->insertOrder(OrderStatus::RESERVED->name, now()->subMinutes(5));
        $this->insertClaimRow($longDead, self::ZONE, isZone: true);
        $this->insertClaimRow($recentlyDead, self::ZONE, isZone: true);

        $this->claim($this->reservedOrder(), [self::ZONE]);

        $this->assertSame(0, DB::table('seat_claims')->where('order_id', $longDead)->count());
        $this->assertSame(1, DB::table('seat_claims')->where('order_id', $recentlyDead)->count());
    }

    public function test_seated_product_availability_is_the_free_seats_in_its_bands(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $unseatedProductId = $this->insertProduct();
        $unseatedPriceId = $this->insertPrice($unseatedProductId, 40);
        $this->claim($this->reservedOrder(), [self::SEAT, self::OTHER_SEAT]);

        $quantities = app(AvailableProductQuantitiesFetchService::class)
            ->getAvailableProductQuantities($this->eventId, ignoreCache: true, eventOccurrenceId: $this->occurrenceId)
            ->productQuantities;

        $this->assertSame(84, $quantities->firstWhere('price_id', $this->priceId)->quantity_available);
        $this->assertSame(40, $quantities->firstWhere('price_id', $unseatedPriceId)->quantity_available);
    }

    public function test_a_cancelled_date_has_no_seated_availability(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['status' => 'CANCELLED']);

        $quantities = app(AvailableProductQuantitiesFetchService::class)
            ->getAvailableProductQuantities($this->eventId, ignoreCache: true, eventOccurrenceId: $this->occurrenceId)
            ->productQuantities;

        $this->assertSame(0, $quantities->firstWhere('price_id', $this->priceId)->quantity_available);
    }

    public function test_releasing_an_attendee_frees_their_seat(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $attendee = $this->seatedAttendee(self::SEAT);

        $this->service->releaseForAttendee($attendee->getId());

        $this->claim($this->reservedOrder(), [self::SEAT]);
        $this->assertSame(1, DB::table('seat_claims')->count());
    }

    public function test_reclaiming_a_seat_that_was_removed_while_released_is_rejected(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $attendee = $this->seatedAttendee(self::SEAT);
        $this->service->releaseForAttendee($attendee->getId());
        $attendee->setSeatUid('e2.99.99');

        $this->expectException(SeatSelectionInvalidException::class);

        $this->service->reclaimForAttendee($attendee, $this->completedOrder($attendee->getOrderId()));
    }

    public function test_reclaiming_a_seat_moved_to_an_unlinked_band_is_rejected(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $attendee = $this->seatedAttendee(self::SEAT);
        $this->service->releaseForAttendee($attendee->getId());
        $attendee->setSeatUid('e3.0.0');

        $this->expectException(SeatSelectionInvalidException::class);

        $this->service->reclaimForAttendee($attendee, $this->completedOrder($attendee->getOrderId()));
    }

    public function test_moving_a_released_attendee_to_an_unseated_ticket_clears_their_seat(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $attendee = $this->seatedAttendee(self::SEAT);
        $this->service->releaseForAttendee($attendee->getId());
        $unseatedProductId = $this->insertProduct();

        $keepsSeat = $this->service->changeProductForAttendee($attendee, $unseatedProductId, $this->insertPrice($unseatedProductId, null));

        $this->assertFalse($keepsSeat);
        $this->assertDatabaseHas('attendees', ['id' => $attendee->getId(), 'seat_uid' => null, 'seat_label' => null]);
    }

    public function test_a_released_attendee_can_move_to_another_ticket_for_the_same_band(): void
    {
        $otherProductId = $this->insertProduct();
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId, $otherProductId]]);
        $attendee = $this->seatedAttendee(self::SEAT);
        $this->service->releaseForAttendee($attendee->getId());

        $this->assertTrue($this->service->changeProductForAttendee($attendee, $otherProductId, $this->insertPrice($otherProductId, null)));
        $this->assertDatabaseHas('attendees', ['id' => $attendee->getId(), 'seat_uid' => self::SEAT]);
    }

    public function test_claims_are_paired_one_to_one_with_attendees_including_shared_zone_uids(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('club'), $this->everyBandLinked());
        $order = $this->reservedOrder();
        $this->claim($order, [self::ZONE, self::ZONE, 'e6.0.0']);

        foreach ([self::ZONE, self::ZONE, 'e6.0.0'] as $seatUid) {
            $this->insertAttendee($order->getId(), $this->productId, $this->priceId, $this->occurrenceId);
            DB::table('attendees')->where('order_id', $order->getId())->whereNull('seat_uid')->limit(1)
                ->update(['seat_uid' => $seatUid]);
        }

        $this->service->pairWithAttendees($order->getId());

        $pairs = DB::table('seat_claims')->where('order_id', $order->getId())->pluck('attendee_id');
        $this->assertCount(3, $pairs->filter()->unique());
    }

    public function test_orphan_rule_is_enforced_only_when_enabled_and_requested(): void
    {
        $eventSeatMapId = $this->insertEventSeatMap($this->seatMapFixture('theatre'), $this->everyBandLinked());
        $strandsFirstSeat = ['e2.0.1', 'e2.0.2'];

        $this->claim($this->reservedOrder(), $strandsFirstSeat, enforceSelectionRules: true);
        DB::table('seat_claims')->delete();

        $this->updateEventSeatMap($eventSeatMapId, ['prevent_orphan_seats' => true]);

        try {
            $this->claim($this->reservedOrder(), $strandsFirstSeat, enforceSelectionRules: true);
            $this->fail('Expected the selection to be rejected');
        } catch (SeatSelectionInvalidException $exception) {
            $this->assertStringContainsString('Stalls · A-1', $exception->getMessage());
        }

        $this->claim($this->reservedOrder(), $strandsFirstSeat);
        $this->assertSame(2, DB::table('seat_claims')->count());
    }

    public function test_orphan_rule_sees_seats_taken_by_other_orders(): void
    {
        $eventSeatMapId = $this->insertEventSeatMap($this->seatMapFixture('theatre'), $this->everyBandLinked());
        $this->updateEventSeatMap($eventSeatMapId, ['prevent_orphan_seats' => true]);
        $this->claim($this->reservedOrder(), ['e2.0.0', 'e2.0.1']);

        $this->expectException(SeatSelectionInvalidException::class);

        $this->claim($this->reservedOrder(), ['e2.0.3', 'e2.0.4'], enforceSelectionRules: true);
    }

    public function test_an_unavoidable_single_seat_is_allowed(): void
    {
        $eventSeatMapId = $this->insertEventSeatMap($this->seatMapFixture('theatre'), $this->everyBandLinked());
        $this->updateEventSeatMap($eventSeatMapId, ['prevent_orphan_seats' => true]);
        $this->claim($this->reservedOrder(), ['e2.0.0', 'e2.0.1', 'e2.0.2', 'e2.0.3']);

        $this->claim($this->reservedOrder(), ['e2.0.4', 'e2.0.5'], enforceSelectionRules: true);

        $this->assertSame(6, DB::table('seat_claims')->count());
    }

    public function test_a_buyer_move_that_strands_a_single_seat_is_rejected(): void
    {
        $eventSeatMapId = $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $this->updateEventSeatMap($eventSeatMapId, ['prevent_orphan_seats' => true]);
        $attendee = $this->seatedAttendee('e2.0.9');

        try {
            $this->service->move($attendee, 'e2.0.1', allowBlocked: false, requireSameBand: true, enforceSelectionRules: true);
            $this->fail('Expected the move to be rejected');
        } catch (SeatSelectionInvalidException $exception) {
            $this->assertStringContainsString('Stalls · A-1', $exception->getMessage());
        }

        $this->assertDatabaseHas('attendees', ['id' => $attendee->getId(), 'seat_uid' => 'e2.0.9']);
        $this->assertSame(['e2.0.9'], DB::table('seat_claims')->pluck('seat_uid')->all());
    }

    public function test_a_buyer_move_ignores_the_seat_it_is_leaving_behind(): void
    {
        $eventSeatMapId = $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $this->updateEventSeatMap($eventSeatMapId, ['prevent_orphan_seats' => true]);
        $attendee = $this->seatedAttendee('e2.0.2');

        $this->service->move($attendee, 'e2.0.0', allowBlocked: false, requireSameBand: true, enforceSelectionRules: true);

        $this->assertDatabaseHas('attendees', ['id' => $attendee->getId(), 'seat_uid' => 'e2.0.0']);
        $this->assertSame(['e2.0.0'], DB::table('seat_claims')->pluck('seat_uid')->all());
    }

    public function test_an_organizer_move_is_not_bound_by_the_orphan_rule(): void
    {
        $eventSeatMapId = $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $this->updateEventSeatMap($eventSeatMapId, ['prevent_orphan_seats' => true]);
        $attendee = $this->seatedAttendee('e2.0.9');

        $this->service->move($attendee, 'e2.0.1', allowBlocked: true, requireSameBand: false);

        $this->assertDatabaseHas('attendees', ['id' => $attendee->getId(), 'seat_uid' => 'e2.0.1']);
    }

    public function test_the_same_seat_twice_on_one_date_is_refused(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), $this->everyBandLinked());
        $order = $this->reservedOrder();

        try {
            $this->claim($order, [self::SEAT, self::OTHER_SEAT, self::SEAT]);
            $this->fail('Expected the duplicate seat to be refused');
        } catch (SeatSelectionInvalidException) {
        }

        $this->assertSame(0, DB::table('seat_claims')->where('order_id', $order->getId())->count());
    }

    public function test_an_order_item_whose_seats_span_two_bands_is_refused(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), $this->everyBandLinked());
        $order = $this->reservedOrder();
        $orderItemId = $this->insertOrderItem($order->getId(), $this->productId, $this->priceId, $this->occurrenceId, 2);

        $this->expectException(SeatSelectionInvalidException::class);

        $this->service->claimForOrder($order, collect([self::SEAT, 'e3.0.0'])->map(fn (string $seatUid) => new SeatSelectionDTO(
            seat_uid: $seatUid,
            event_occurrence_id: $this->occurrenceId,
            product_id: $this->productId,
            product_price_id: $this->priceId,
            order_item_id: $orderItemId,
        )));
    }

    private function claim(OrderDomainObject $order, array $seatUids, ?int $occurrenceId = null, bool $enforceSelectionRules = false): void
    {
        $this->service->claimForOrder($order, collect($seatUids)->map(fn (string $seatUid) => new SeatSelectionDTO(
            seat_uid: $seatUid,
            event_occurrence_id: $occurrenceId ?? $this->occurrenceId,
            product_id: $this->productId,
            product_price_id: $this->priceId,
        )), $enforceSelectionRules);
    }

    private function seatedAttendee(string $seatUid): AttendeeDomainObject
    {
        $order = $this->completedOrder($this->insertOrder(OrderStatus::COMPLETED->name));
        $this->claim($order, [$seatUid]);
        $this->insertAttendee($order->getId(), $this->productId, $this->priceId, $this->occurrenceId);
        DB::table('attendees')->where('order_id', $order->getId())->update(['seat_uid' => $seatUid, 'seat_label' => 'Stalls · A-1']);
        $this->service->pairWithAttendees($order->getId());

        return (new AttendeeDomainObject)
            ->setId(DB::table('attendees')->where('order_id', $order->getId())->value('id'))
            ->setOrderId($order->getId())
            ->setEventId($this->eventId)
            ->setEventOccurrenceId($this->occurrenceId)
            ->setProductId($this->productId)
            ->setProductPriceId($this->priceId)
            ->setSeatUid($seatUid)
            ->setSeatLabel('Stalls · A-1');
    }

    private function completedOrder(int $orderId): OrderDomainObject
    {
        return (new OrderDomainObject)->setId($orderId)->setEventId($this->eventId);
    }

    private function reservedOrder(): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId($this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15)))
            ->setEventId($this->eventId);
    }

    private function everyBandLinked(): array
    {
        return array_fill_keys(['b_premium', 'b_standard', 'b_value'], [$this->productId]);
    }

    private function insertClaimRow(int $orderId, string $seatUid, bool $isZone = false): void
    {
        DB::table('seat_claims')->insert([
            'event_id' => $this->eventId,
            'event_occurrence_id' => $this->occurrenceId,
            'seat_uid' => $seatUid,
            'is_zone' => $isZone,
            'band_key' => 'b_premium',
            'seat_label' => 'Test',
            'order_id' => $orderId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
