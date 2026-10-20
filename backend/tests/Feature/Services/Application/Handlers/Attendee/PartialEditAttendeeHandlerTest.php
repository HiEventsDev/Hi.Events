<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Services\Application\Handlers\Attendee\DTO\PartialEditAttendeeDTO;
use HiEvents\Services\Application\Handlers\Attendee\PartialEditAttendeeHandler;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class PartialEditAttendeeHandlerTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const SEAT = 'e2.0.0';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private ?int $capacityAssignmentId = null;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Mail::fake();
        $this->app->instance(DomainEventDispatcherService::class, Mockery::mock(DomainEventDispatcherService::class)->shouldIgnoreMissing());

        $this->insertAccountAndOrganizer();
    }

    public function test_a_cancelled_attendee_is_not_reactivated_once_the_price_is_sold_out(): void
    {
        $this->givenSingleEvent(priceQuantity: 1);
        $this->liveAttendee();
        $attendeeId = $this->cancelledAttendee();

        $message = $this->assertRefused($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertSame(__('This ticket is sold out, so this attendee cannot be reactivated. Increase its quantity and try again.'), $message);

        $this->assertCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 1);
    }

    public function test_a_cancelled_attendee_is_not_reactivated_once_its_capacity_assignment_is_full(): void
    {
        $this->givenSingleEvent();
        $this->insertCapacityAssignment(capacity: 1, usedCapacity: 1);
        $this->liveAttendee();
        $attendeeId = $this->cancelledAttendee();

        $message = $this->assertRefused($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertSame(__('The capacity shared by this ticket is full.'), $message);
        $this->assertCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 1, poolUsed: 1);
    }

    public function test_a_cancelled_attendee_is_not_reactivated_once_its_date_is_full(): void
    {
        $this->givenSingleEvent(occurrenceCapacity: 1);
        $this->liveAttendee();
        $attendeeId = $this->cancelledAttendee();

        $message = $this->assertRefused($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertSame(__('This event is sold out'), $message);
        $this->assertCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 1);
    }

    public function test_a_cancelled_attendee_is_not_reactivated_onto_a_cancelled_date(): void
    {
        $this->givenSingleEvent();
        $attendeeId = $this->cancelledAttendee();
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['status' => EventOccurrenceStatus::CANCELLED->name]);

        $message = $this->assertRefused($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertSame(__('This event has been cancelled'), $message);
        $this->assertCounters(priceSold: 0, occurrenceUsed: 0, attendeesRegistered: 0);
    }

    public function test_a_cancelled_attendee_is_not_reactivated_once_the_per_date_quantity_of_their_date_is_used(): void
    {
        $this->givenRecurringEventWithPerDateQuantity(perDateQuantity: 1);
        $this->liveAttendee();
        $attendeeId = $this->cancelledAttendee();

        $this->assertRefused($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 1);
    }

    public function test_a_full_date_does_not_stop_a_reactivation_on_another_date_with_room_for_a_per_date_quantity(): void
    {
        $this->givenRecurringEventWithPerDateQuantity(perDateQuantity: 1);
        $this->liveAttendee();
        $otherDateId = $this->insertOccurrence(daysAhead: 8);
        $attendeeId = $this->cancelledAttendee($otherDateId);

        $this->changeStatus($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertSame(AttendeeStatus::ACTIVE->name, $this->statusOf($attendeeId));
        $this->assertSame(2, $this->priceSold());
        $this->assertSame(1, $this->occurrenceUsed());
        $this->assertSame(1, (int) DB::table('event_occurrences')->where('id', $otherDateId)->value('used_capacity'));
    }

    public function test_a_cancelled_attendee_is_not_reactivated_while_a_reserved_checkout_holds_the_last_place_in_its_capacity_assignment(): void
    {
        $this->givenSingleEvent();
        $this->insertCapacityAssignment(capacity: 2, usedCapacity: 1);
        $this->liveAttendee();
        $this->reserve($this->productId, $this->priceId, $this->occurrenceId, 1);
        $attendeeId = $this->cancelledAttendee();

        $message = $this->assertRefused($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertSame(__('The capacity shared by this ticket is full.'), $message);
        $this->assertCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 1, poolUsed: 1);
    }

    public function test_a_cancelled_attendee_is_not_reactivated_while_a_waitlist_offer_holds_the_last_place_on_their_date(): void
    {
        $this->givenSingleEvent(occurrenceCapacity: 2);
        $this->liveAttendee();
        $offerOrderId = $this->reserve($this->productId, $this->priceId, $this->occurrenceId, 1);
        DB::table('waitlist_entries')->insert([
            'event_id' => $this->eventId,
            'product_price_id' => $this->priceId,
            'event_occurrence_id' => $this->occurrenceId,
            'email' => 'waiting@example.test',
            'first_name' => 'Waiting',
            'status' => WaitlistEntryStatus::OFFERED->name,
            'offer_token' => 'offer_'.uniqid(),
            'offered_at' => now(),
            'offer_expires_at' => now()->addMinutes(15),
            'order_id' => $offerOrderId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $attendeeId = $this->cancelledAttendee();

        $message = $this->assertRefused($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertSame(__('Not enough capacity available for this event'), $message);
        $this->assertCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 1);
    }

    public function test_a_cancelled_attendee_is_reactivated_when_there_is_room_and_every_count_moves_exactly_once(): void
    {
        $this->givenSingleEvent(priceQuantity: 5, occurrenceCapacity: 5);
        $this->insertCapacityAssignment(capacity: 5, usedCapacity: 1);
        $this->liveAttendee();
        $attendeeId = $this->cancelledAttendee();

        $this->changeStatus($attendeeId, AttendeeStatus::ACTIVE);
        $this->changeStatus($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertSame(AttendeeStatus::ACTIVE->name, $this->statusOf($attendeeId));
        $this->assertCounters(priceSold: 2, occurrenceUsed: 2, attendeesRegistered: 2, poolUsed: 2);
    }

    public function test_the_last_place_can_be_taken_by_a_reactivation(): void
    {
        $this->givenSingleEvent(priceQuantity: 2, occurrenceCapacity: 2);
        $this->insertCapacityAssignment(capacity: 2, usedCapacity: 1);
        $this->liveAttendee();
        $attendeeId = $this->cancelledAttendee();

        $this->changeStatus($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertCounters(priceSold: 2, occurrenceUsed: 2, attendeesRegistered: 2, poolUsed: 2);
    }

    public function test_moving_between_active_and_awaiting_payment_leaves_counters_and_statistics_alone(): void
    {
        $this->givenSingleEvent(priceQuantity: 1, occurrenceCapacity: 1);
        $orderId = $this->insertOrder(OrderStatus::AWAITING_OFFLINE_PAYMENT->name);
        $this->insertOrderItem($orderId, $this->productId, $this->priceId, $this->occurrenceId, 1);
        $this->insertAttendee($orderId, $this->productId, $this->priceId, $this->occurrenceId, AttendeeStatus::AWAITING_PAYMENT->name);
        $attendeeId = $this->attendeeOf($orderId);
        $this->setCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 0);

        $this->changeStatus($attendeeId, AttendeeStatus::ACTIVE);
        $this->assertSame(AttendeeStatus::ACTIVE->name, $this->statusOf($attendeeId));
        $this->assertCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 0);

        $this->changeStatus($attendeeId, AttendeeStatus::AWAITING_PAYMENT);
        $this->assertSame(AttendeeStatus::AWAITING_PAYMENT->name, $this->statusOf($attendeeId));
        $this->assertCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 0);
    }

    public function test_bringing_a_cancelled_attendee_back_as_awaiting_payment_is_refused_once_the_price_is_sold_out(): void
    {
        $this->givenSingleEvent(priceQuantity: 1);
        $this->liveAttendee();
        $attendeeId = $this->cancelledAttendee(orderStatus: OrderStatus::AWAITING_OFFLINE_PAYMENT->name);

        $this->assertRefused($attendeeId, AttendeeStatus::AWAITING_PAYMENT);

        $this->assertCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 1);
    }

    public function test_bringing_a_cancelled_attendee_back_as_awaiting_payment_counts_them_again(): void
    {
        $this->givenSingleEvent(priceQuantity: 5, occurrenceCapacity: 5);
        $this->liveAttendee();
        $attendeeId = $this->cancelledAttendee(orderStatus: OrderStatus::AWAITING_OFFLINE_PAYMENT->name);

        $this->changeStatus($attendeeId, AttendeeStatus::AWAITING_PAYMENT);

        $this->assertSame(AttendeeStatus::AWAITING_PAYMENT->name, $this->statusOf($attendeeId));
        $this->assertCounters(priceSold: 2, occurrenceUsed: 2, attendeesRegistered: 2);
    }

    public function test_cancelling_an_attendee_still_frees_their_place_once(): void
    {
        $this->givenSingleEvent(priceQuantity: 1, occurrenceCapacity: 1);
        $this->insertCapacityAssignment(capacity: 1, usedCapacity: 1);
        $attendeeId = $this->liveAttendee();

        $this->changeStatus($attendeeId, AttendeeStatus::CANCELLED);
        $this->changeStatus($attendeeId, AttendeeStatus::CANCELLED);

        $this->assertSame(AttendeeStatus::CANCELLED->name, $this->statusOf($attendeeId));
        $this->assertCounters(priceSold: 0, occurrenceUsed: 0, attendeesRegistered: 0, poolUsed: 0);
    }

    public function test_a_seated_attendee_whose_seat_is_free_is_not_reactivated_when_the_date_is_full(): void
    {
        $this->givenSingleEvent(occurrenceCapacity: 1);
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $this->liveAttendee();
        $attendeeId = $this->cancelledAttendee();
        DB::table('attendees')->where('id', $attendeeId)->update(['seat_uid' => self::SEAT, 'seat_label' => 'Stalls · A-1']);

        $this->assertRefused($attendeeId, AttendeeStatus::ACTIVE);

        $this->assertSame(0, DB::table('seat_claims')->where('attendee_id', $attendeeId)->count());
        $this->assertCounters(priceSold: 1, occurrenceUsed: 1, attendeesRegistered: 1);
    }

    private function givenSingleEvent(?int $priceQuantity = null, ?int $occurrenceCapacity = null): void
    {
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $this->occurrenceId = $this->insertOccurrence(capacity: $occurrenceCapacity);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, $priceQuantity, ProductQuantityAppliesTo::EVENT->name);
        $this->insertEventStatistics();
    }

    private function givenRecurringEventWithPerDateQuantity(int $perDateQuantity): void
    {
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, $perDateQuantity, ProductQuantityAppliesTo::OCCURRENCE->name);
        $this->insertEventStatistics();
    }

    private function insertEventStatistics(): void
    {
        DB::table('event_statistics')->insert([
            'event_id' => $this->eventId,
            'products_sold' => 0,
            'attendees_registered' => 0,
            'orders_created' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertCapacityAssignment(int $capacity, int $usedCapacity): void
    {
        $this->capacityAssignmentId = DB::table('capacity_assignments')->insertGetId([
            'event_id' => $this->eventId,
            'name' => 'Shared pool',
            'capacity' => $capacity,
            'used_capacity' => $usedCapacity,
            'applies_to' => 'PRODUCTS',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_capacity_assignments')->insert([
            'product_id' => $this->productId,
            'capacity_assignment_id' => $this->capacityAssignmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function liveAttendee(): int
    {
        $orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->insertOrderItem($orderId, $this->productId, $this->priceId, $this->occurrenceId, 1);
        $this->insertAttendee($orderId, $this->productId, $this->priceId, $this->occurrenceId);

        DB::table('product_prices')->where('id', $this->priceId)->increment('quantity_sold');
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->increment('used_capacity');
        DB::table('event_statistics')->where('event_id', $this->eventId)->increment('attendees_registered');

        return $this->attendeeOf($orderId);
    }

    private function cancelledAttendee(?int $occurrenceId = null, string $orderStatus = OrderStatus::COMPLETED->name): int
    {
        $occurrenceId ??= $this->occurrenceId;
        $orderId = $this->insertOrder($orderStatus);
        $this->insertOrderItem($orderId, $this->productId, $this->priceId, $occurrenceId, 1);
        $this->insertAttendee($orderId, $this->productId, $this->priceId, $occurrenceId, AttendeeStatus::CANCELLED->name);

        return $this->attendeeOf($orderId);
    }

    private function attendeeOf(int $orderId): int
    {
        return (int) DB::table('attendees')->where('order_id', $orderId)->value('id');
    }

    private function changeStatus(int $attendeeId, AttendeeStatus $status): void
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

    private function assertRefused(int $attendeeId, AttendeeStatus $status): string
    {
        try {
            $this->changeStatus($attendeeId, $status);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
            $this->assertSame(AttendeeStatus::CANCELLED->name, $this->statusOf($attendeeId));

            return $exception->errors()['status'][0];
        }

        $this->fail('Expected the reactivation to be refused');
    }

    private function statusOf(int $attendeeId): string
    {
        return DB::table('attendees')->where('id', $attendeeId)->value('status');
    }

    private function priceSold(): int
    {
        return (int) DB::table('product_prices')->where('id', $this->priceId)->value('quantity_sold');
    }

    private function occurrenceUsed(): int
    {
        return (int) DB::table('event_occurrences')->where('id', $this->occurrenceId)->value('used_capacity');
    }

    private function setCounters(int $priceSold, int $occurrenceUsed, int $attendeesRegistered): void
    {
        DB::table('product_prices')->where('id', $this->priceId)->update(['quantity_sold' => $priceSold]);
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['used_capacity' => $occurrenceUsed]);
        DB::table('event_statistics')->where('event_id', $this->eventId)->update(['attendees_registered' => $attendeesRegistered]);
    }

    private function assertCounters(int $priceSold, int $occurrenceUsed, int $attendeesRegistered, ?int $poolUsed = null): void
    {
        $this->assertSame([
            'price_sold' => $priceSold,
            'occurrence_used' => $occurrenceUsed,
            'attendees_registered' => $attendeesRegistered,
            'pool_used' => $poolUsed,
        ], [
            'price_sold' => $this->priceSold(),
            'occurrence_used' => $this->occurrenceUsed(),
            'attendees_registered' => (int) DB::table('event_statistics')->where('event_id', $this->eventId)->value('attendees_registered'),
            'pool_used' => $this->capacityAssignmentId === null
                ? null
                : (int) DB::table('capacity_assignments')->where('id', $this->capacityAssignmentId)->value('used_capacity'),
        ]);
    }
}
