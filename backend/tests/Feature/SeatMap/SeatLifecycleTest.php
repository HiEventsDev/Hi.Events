<?php

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BandProductLinkDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BandProductsDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\UpdateEventSeatMapBandProductsHandler;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\OccupiedSeatDTO;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatSelectionDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatAvailabilityService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\PartialEditAttendeeDTO;
use HiEvents\Services\Application\Handlers\Attendee\PartialEditAttendeeHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\CancelOccurrenceHandler;
use HiEvents\Services\Application\Handlers\EventOccurrence\DeleteEventOccurrenceHandler;
use HiEvents\Services\Domain\Event\EventOccurrenceGeneratorService;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\Product\DeleteProductService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class SeatLifecycleTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const SEAT = 'e2.0.0';

    private const ZONE = 'z2';

    private int $occurrenceId;

    private int $secondOccurrenceId;

    private int $productId;

    private int $priceId;

    private SeatClaimService $seatClaimService;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        $this->occurrenceId = $this->insertOccurrence();
        $this->secondOccurrenceId = $this->insertOccurrence(daysAhead: 8);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null);
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);

        DB::table('event_statistics')->insert([
            'event_id' => $this->eventId,
            'products_sold' => 10,
            'attendees_registered' => 10,
            'orders_created' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seatClaimService = app(SeatClaimService::class);
    }

    public function test_cancelling_a_date_releases_its_seat_claims_and_leaves_other_dates_untouched(): void
    {
        $attendeeId = $this->sellSeat(self::SEAT, $this->occurrenceId);
        $otherDateAttendeeId = $this->sellSeat(self::SEAT, $this->secondOccurrenceId);

        app(CancelOccurrenceHandler::class)->handle($this->eventId, $this->occurrenceId);

        $this->assertSame(0, DB::table('seat_claims')->where('attendee_id', $attendeeId)->count());
        $this->assertSame([], $this->occupiedSeatUids($this->occurrenceId));
        $this->assertSame([self::SEAT], $this->occupiedSeatUids($this->secondOccurrenceId));
        $this->assertSame(1, DB::table('seat_claims')->where('attendee_id', $otherDateAttendeeId)->count());

        $buyer = $this->reservedOrder();
        $this->claim($buyer, [self::SEAT], $this->occurrenceId);

        $this->assertSame(
            [$buyer->getId()],
            DB::table('seat_claims')->where('event_occurrence_id', $this->occurrenceId)->pluck('order_id')->all(),
        );
    }

    public function test_a_refund_only_frees_the_seat_once_the_order_is_cancelled_too(): void
    {
        $attendeeId = $this->sellSeat(self::SEAT, $this->occurrenceId);
        $orderId = (int) DB::table('attendees')->where('id', $attendeeId)->value('order_id');

        DB::table('orders')->where('id', $orderId)->update([
            'refund_status' => OrderRefundStatus::REFUNDED->name,
        ]);

        $this->assertSame([self::SEAT], $this->occupiedSeatUids($this->occurrenceId));
        try {
            $this->claim($this->reservedOrder(), [self::SEAT], $this->occurrenceId);
            $this->fail('A refunded but uncancelled order must keep its seat');
        } catch (SeatsUnavailableException $exception) {
            $this->assertSame([self::SEAT], $exception->getSeatUids());
        }

        app(OrderCancelService::class)->cancelOrder(app(OrderRepositoryInterface::class)->findById($orderId));

        $this->assertSame([], $this->occupiedSeatUids($this->occurrenceId));

        $buyer = $this->reservedOrder();
        $this->claim($buyer, [self::SEAT], $this->occurrenceId);

        $this->assertSame(
            [$buyer->getId()],
            DB::table('seat_claims')->where('event_occurrence_id', $this->occurrenceId)->pluck('order_id')->all(),
        );
    }

    public function test_deleting_a_seated_product_drops_its_band_link_and_the_remaining_links_still_save(): void
    {
        $secondProductId = $this->insertProduct();
        $this->insertPrice($secondProductId, null);
        $this->saveBandProducts([$this->productId, $secondProductId]);

        app(DeleteProductService::class)->deleteProduct($secondProductId, $this->eventId);

        $this->assertSame(0, DB::table('event_seat_map_band_products')->where('product_id', $secondProductId)->count());

        $updated = $this->saveBandProducts([$this->productId]);

        $this->assertSame(
            [$this->productId],
            collect($updated->getEventSeatMapBandProducts())
                ->map(fn (EventSeatMapBandProductDomainObject $link) => $link->getProductId())
                ->all(),
        );
    }

    public function test_deleting_a_date_removes_the_seats_held_back_on_it(): void
    {
        $occurrenceId = $this->insertOccurrence(daysAhead: 5);
        $this->block($occurrenceId, self::SEAT);

        app(DeleteEventOccurrenceHandler::class)->handle($this->eventId, $occurrenceId);

        $this->assertSame(0, DB::table('seat_claims')->where('event_occurrence_id', $occurrenceId)->count());
        $this->assertSame(0, DB::table('event_occurrences')->where('id', $occurrenceId)->whereNull('deleted_at')->count());
    }

    public function test_regeneration_keeps_a_date_whose_only_content_is_held_back_seats(): void
    {
        $generator = app(EventOccurrenceGeneratorService::class);
        $generator->generate($this->event(), $this->weeklyRule(3));
        $generated = $this->generatedOccurrenceIds();
        $droppedDateId = end($generated);
        $this->block($droppedDateId, self::SEAT);

        $generator->generate($this->event(), $this->weeklyRule(2));

        $this->assertDatabaseHas('event_occurrences', [
            'id' => $droppedDateId,
            'deleted_at' => null,
            'is_overridden' => true,
        ]);
        $this->assertSame(1, DB::table('seat_claims')->where('event_occurrence_id', $droppedDateId)->count());
    }

    public function test_a_cancelled_attendee_is_only_reactivated_while_their_seat_is_still_free(): void
    {
        $attendeeId = $this->sellSeat(self::SEAT, $this->occurrenceId);

        $this->changeAttendeeStatus($attendeeId, AttendeeStatus::CANCELLED);
        $this->assertSame(0, DB::table('seat_claims')->where('attendee_id', $attendeeId)->count());

        $this->changeAttendeeStatus($attendeeId, AttendeeStatus::ACTIVE);
        $this->assertSame(1, DB::table('seat_claims')->where('attendee_id', $attendeeId)->count());

        $this->changeAttendeeStatus($attendeeId, AttendeeStatus::CANCELLED);
        $this->claim($this->reservedOrder(), [self::SEAT], $this->occurrenceId);

        try {
            $this->changeAttendeeStatus($attendeeId, AttendeeStatus::ACTIVE);
            $this->fail('Expected the reactivation to be rejected');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertDatabaseHas('attendees', ['id' => $attendeeId, 'status' => AttendeeStatus::CANCELLED->name]);
        $this->assertSame(0, DB::table('seat_claims')->where('attendee_id', $attendeeId)->count());
    }

    public function test_a_reactivated_attendee_gets_their_seat_back_on_their_order_item_under_its_current_label(): void
    {
        $attendeeId = $this->sellSeat(self::SEAT, $this->occurrenceId);
        $orderId = (int) DB::table('attendees')->where('id', $attendeeId)->value('order_id');
        $this->changeAttendeeStatus($attendeeId, AttendeeStatus::CANCELLED);

        $layout = $this->seatMapFixture('theatre');
        $layout['areas'][0]['name'] = 'Orchestra';
        $this->updateEventSeatMap(DB::table('event_seat_maps')->where('event_id', $this->eventId)->value('id'), ['layout' => json_encode($layout)]);

        $this->changeAttendeeStatus($attendeeId, AttendeeStatus::ACTIVE);

        $claim = DB::table('seat_claims')->where('attendee_id', $attendeeId)->first();
        $this->assertSame((int) DB::table('order_items')->where('order_id', $orderId)->value('id'), (int) $claim->order_item_id);
        $this->assertSame('Orchestra · A-1', $claim->seat_label);
        $this->assertSame('Orchestra · A-1', DB::table('attendees')->where('id', $attendeeId)->value('seat_label'));
    }

    public function test_claiming_after_the_seat_map_has_been_detached_is_refused_cleanly(): void
    {
        DB::table('event_seat_map_band_products')->delete();
        $this->deleteEventSeatMapOf($this->eventId);

        $this->expectException(SeatSelectionInvalidException::class);

        $this->claim($this->reservedOrder(), [self::SEAT], $this->occurrenceId);
    }

    public function test_pairing_never_gives_one_attendee_a_second_claim(): void
    {
        $this->useClubLayout();
        $order = $this->reservedOrder();

        $this->claim($order, [self::ZONE], $this->occurrenceId);
        $this->insertAttendee($order->getId(), $this->productId, $this->priceId, $this->occurrenceId);
        DB::table('attendees')->where('order_id', $order->getId())->update([
            'seat_uid' => self::ZONE,
            'seat_label' => 'Main floor · Front pit',
        ]);
        $this->seatClaimService->pairWithAttendees($order->getId());

        $this->claim($order, [self::ZONE], $this->occurrenceId);
        $this->seatClaimService->pairWithAttendees($order->getId());

        $this->assertSame(2, DB::table('seat_claims')->where('order_id', $order->getId())->count());
        $this->assertSame(1, DB::table('seat_claims')->where('order_id', $order->getId())->whereNotNull('attendee_id')->count());
    }

    public function test_reactivating_one_ticket_never_hands_its_zone_place_to_a_cancelled_sibling(): void
    {
        $this->useClubLayout();
        $order = (new OrderDomainObject)
            ->setId($this->insertOrder(OrderStatus::COMPLETED->name))
            ->setEventId($this->eventId);
        $this->insertOrderItem($order->getId(), $this->productId, $this->priceId, $this->occurrenceId, 2);
        $this->claim($order, [self::ZONE, self::ZONE], $this->occurrenceId);
        $this->insertAttendee($order->getId(), $this->productId, $this->priceId, $this->occurrenceId);
        $this->insertAttendee($order->getId(), $this->productId, $this->priceId, $this->occurrenceId);
        DB::table('attendees')->where('order_id', $order->getId())->update([
            'seat_uid' => self::ZONE,
            'seat_label' => 'Main floor · Front pit',
        ]);
        $this->seatClaimService->pairWithAttendees($order->getId());
        [$first, $second] = DB::table('attendees')->where('order_id', $order->getId())->orderBy('id')->pluck('id')->all();

        $this->changeAttendeeStatus($first, AttendeeStatus::CANCELLED);
        $this->changeAttendeeStatus($second, AttendeeStatus::CANCELLED);
        $this->changeAttendeeStatus($second, AttendeeStatus::ACTIVE);

        $this->assertSame([$second], DB::table('seat_claims')->where('order_id', $order->getId())->pluck('attendee_id')->all());
    }

    public function test_a_cancelled_seated_attendee_can_only_come_back_as_active(): void
    {
        $attendeeId = $this->sellSeat(self::SEAT, $this->occurrenceId);
        $this->changeAttendeeStatus($attendeeId, AttendeeStatus::CANCELLED);

        try {
            $this->changeAttendeeStatus($attendeeId, AttendeeStatus::AWAITING_PAYMENT);
            $this->fail('Expected the status change to be rejected');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertDatabaseHas('attendees', ['id' => $attendeeId, 'status' => AttendeeStatus::CANCELLED->name]);
    }

    private function useClubLayout(): void
    {
        DB::table('event_seat_map_band_products')->delete();
        $this->deleteEventSeatMapOf($this->eventId);
        $this->insertEventSeatMap($this->seatMapFixture('club'), ['b_premium' => [$this->productId]]);
    }

    private function changeAttendeeStatus(int $attendeeId, AttendeeStatus $status): void
    {
        app(PartialEditAttendeeHandler::class)->handle(new PartialEditAttendeeDTO(
            attendee_id: $attendeeId,
            event_id: $this->eventId,
            first_name: null,
            last_name: null,
            email: null,
            status: $status->name,
        ));
    }

    private function saveBandProducts(array $productIds): EventSeatMapDomainObject
    {
        return app(UpdateEventSeatMapBandProductsHandler::class)->handle($this->eventId, collect([
            new BandProductsDTO(
                band_key: 'b_premium',
                products: collect($productIds)->map(fn (int $productId) => new BandProductLinkDTO(product_id: $productId)),
            ),
        ]));
    }

    private function block(int $occurrenceId, string $seatUid): void
    {
        DB::transaction(fn () => $this->seatClaimService->block($this->eventId, [$occurrenceId], [$seatUid], 'Held back'));
    }

    private function weeklyRule(int $count): array
    {
        return [
            'frequency' => 'weekly',
            'interval' => 1,
            'days_of_week' => ['monday'],
            'range' => ['type' => 'count', 'count' => $count, 'start' => '2030-06-03'],
            'times_of_day' => ['19:00'],
            'duration_minutes' => 120,
        ];
    }

    /**
     * @return int[]
     */
    private function generatedOccurrenceIds(): array
    {
        return DB::table('event_occurrences')
            ->where('event_id', $this->eventId)
            ->whereNull('deleted_at')
            ->where('start_date', '>=', '2030-01-01')
            ->orderBy('start_date')
            ->pluck('id')
            ->all();
    }

    private function event(): EventDomainObject
    {
        return app(EventRepositoryInterface::class)->findById($this->eventId);
    }

    /**
     * @return string[]
     */
    private function occupiedSeatUids(int $occurrenceId): array
    {
        return app(SeatAvailabilityService::class)->occupiedSeats($occurrenceId)
            ->map(fn (OccupiedSeatDTO $seat) => $seat->seat_uid)
            ->all();
    }

    private function sellSeat(string $seatUid, int $occurrenceId): int
    {
        $order = (new OrderDomainObject)
            ->setId($this->insertOrder(OrderStatus::COMPLETED->name))
            ->setEventId($this->eventId);

        $this->insertOrderItem($order->getId(), $this->productId, $this->priceId, $occurrenceId, 1);
        $this->claim($order, [$seatUid], $occurrenceId);
        $this->insertAttendee($order->getId(), $this->productId, $this->priceId, $occurrenceId);

        $attendeeId = (int) DB::table('attendees')->where('order_id', $order->getId())->value('id');
        DB::table('attendees')->where('id', $attendeeId)->update([
            'seat_uid' => $seatUid,
            'seat_label' => 'Stalls · A-1',
        ]);
        $this->seatClaimService->pairWithAttendees($order->getId());

        return $attendeeId;
    }

    private function reservedOrder(): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId($this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15)))
            ->setEventId($this->eventId);
    }

    private function claim(OrderDomainObject $order, array $seatUids, int $occurrenceId): void
    {
        $this->seatClaimService->claimForOrder($order, collect($seatUids)->map(fn (string $seatUid) => new SeatSelectionDTO(
            seat_uid: $seatUid,
            event_occurrence_id: $occurrenceId,
            product_id: $this->productId,
            product_price_id: $this->priceId,
        )));
    }
}
