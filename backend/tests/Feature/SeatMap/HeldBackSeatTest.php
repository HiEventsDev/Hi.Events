<?php

declare(strict_types=1);

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\SeatClaimStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\OccupiedSeatDTO;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatSelectionDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatAvailabilityService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Services\Application\Handlers\Attendee\CreateAttendeeHandler;
use HiEvents\Services\Application\Handlers\Attendee\DTO\CreateAttendeeDTO;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class HeldBackSeatTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const SEAT = 'e2.0.0';

    private const OTHER_SEAT = 'e2.0.5';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private SeatClaimService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Mail::fake();
        $this->app->instance(DomainEventDispatcherService::class, Mockery::mock(DomainEventDispatcherService::class)->shouldIgnoreMissing());

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null, price: 30.00);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $this->service = app(SeatClaimService::class);

        $this->service->block($this->eventId, [$this->occurrenceId], [self::SEAT], 'Wheelchair spare');
    }

    public function test_a_sold_held_seat_shows_as_sold_and_keeps_its_reason(): void
    {
        $this->createAttendee();

        $seat = $this->occupied(self::SEAT);
        $this->assertSame(SeatClaimStatus::SOLD, $seat->status);
        $this->assertSame(1, DB::table('seat_claims')->where('seat_uid', self::SEAT)->count());
    }

    public function test_cancelling_an_attendee_on_a_held_seat_puts_the_hold_back(): void
    {
        $attendee = $this->createAttendee();

        $this->service->releaseForAttendee($attendee->getId());

        $this->assertHeldBack(self::SEAT);
        $this->assertDatabaseHas('seat_claims', ['seat_uid' => self::SEAT, 'order_id' => null, 'attendee_id' => null, 'product_id' => null]);
    }

    public function test_cancelling_the_order_of_a_held_seat_puts_the_hold_back(): void
    {
        $attendee = $this->createAttendee();

        DB::table('orders')->where('id', $attendee->getOrderId())->update(['status' => OrderStatus::CANCELLED->name]);

        $this->assertHeldBack(self::SEAT);
        $this->assertTrue(in_array(self::SEAT, app(SeatAvailabilityService::class)->forOccurrence($this->eventId, $this->occurrenceId)->unavailable_seat_uids, true));
    }

    public function test_an_expired_door_sale_on_a_held_seat_is_not_purged_by_the_next_buyer(): void
    {
        $order = $this->order(OrderStatus::RESERVED, now()->addMinutes(15));
        $this->claim($order, [self::SEAT], allowBlocked: true);
        DB::table('orders')->where('id', $order->getId())->update(['reserved_until' => now()->subMinute()]);

        try {
            $this->claim($this->order(OrderStatus::RESERVED, now()->addMinutes(15)), [self::SEAT]);
            $this->fail('A public buyer must not get a held-back seat');
        } catch (SeatsUnavailableException $exception) {
            $this->assertSame([self::SEAT], $exception->getSeatUids());
        }

        $this->assertHeldBack(self::SEAT);
    }

    public function test_the_door_can_resell_a_held_seat_after_its_first_sale_was_voided(): void
    {
        $first = $this->order(OrderStatus::RESERVED, now()->addMinutes(15));
        $this->claim($first, [self::SEAT], allowBlocked: true);
        DB::table('orders')->where('id', $first->getId())->update(['status' => OrderStatus::ABANDONED->name]);

        $second = $this->order(OrderStatus::COMPLETED);
        $this->claim($second, [self::SEAT], allowBlocked: true);

        $this->assertSame([$second->getId()], DB::table('seat_claims')->where('seat_uid', self::SEAT)->pluck('order_id')->all());
        DB::table('orders')->where('id', $second->getId())->update(['status' => OrderStatus::CANCELLED->name]);
        $this->assertHeldBack(self::SEAT);
    }

    public function test_releasing_the_hold_of_a_sold_seat_frees_it_when_the_sale_is_voided(): void
    {
        $attendee = $this->createAttendee();

        $this->assertSame(1, $this->service->unblock([$this->occurrenceId], [self::SEAT]));
        $this->assertSame(SeatClaimStatus::SOLD, $this->occupied(self::SEAT)->status);

        $this->service->releaseForAttendee($attendee->getId());

        $this->assertNull($this->occupied(self::SEAT));
    }

    public function test_reblocking_a_sold_held_seat_keeps_the_hold(): void
    {
        $attendee = $this->createAttendee();

        $result = $this->service->block($this->eventId, [$this->occurrenceId], [self::SEAT], 'Other reason');

        $this->assertSame(0, $result->blocked);
        $this->service->releaseForAttendee($attendee->getId());
        $this->assertHeldBack(self::SEAT);
    }

    public function test_an_organizer_move_off_a_held_seat_restores_it_and_carries_the_new_seats_hold(): void
    {
        $this->service->block($this->eventId, [$this->occurrenceId], [self::OTHER_SEAT], 'Press');
        $attendee = $this->createAttendee();

        $this->service->move($attendee, self::OTHER_SEAT, allowBlocked: true, requireSameBand: true);

        $this->assertHeldBack(self::SEAT);
        $this->assertSame(SeatClaimStatus::SOLD, $this->occupied(self::OTHER_SEAT)->status);

        $this->service->releaseForAttendee($attendee->getId());

        $this->assertHeldBack(self::OTHER_SEAT, 'Press');
    }

    private function assertHeldBack(string $seatUid, string $reason = 'Wheelchair spare'): void
    {
        $seat = $this->occupied($seatUid);

        $this->assertNotNull($seat, "$seatUid is free");
        $this->assertSame(SeatClaimStatus::BLOCKED, $seat->status);
        $this->assertSame($reason, $seat->block_reason);
    }

    private function occupied(string $seatUid): ?OccupiedSeatDTO
    {
        return app(SeatAvailabilityService::class)
            ->occupiedSeats($this->occurrenceId)
            ->first(fn (OccupiedSeatDTO $seat) => $seat->seat_uid === $seatUid);
    }

    private function claim(OrderDomainObject $order, array $seatUids, bool $allowBlocked = false): void
    {
        $this->service->claimForOrder($order, collect($seatUids)->map(fn (string $seatUid) => new SeatSelectionDTO(
            seat_uid: $seatUid,
            event_occurrence_id: $this->occurrenceId,
            product_id: $this->productId,
            product_price_id: $this->priceId,
        )), allowBlocked: $allowBlocked);
    }

    private function order(OrderStatus $status, ?\DateTimeInterface $reservedUntil = null): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId($this->insertOrder($status->name, $reservedUntil))
            ->setEventId($this->eventId);
    }

    private function createAttendee(): AttendeeDomainObject
    {
        return app(CreateAttendeeHandler::class)->handle(new CreateAttendeeDTO(
            first_name: 'Walk',
            last_name: 'In',
            email: 'walkin@example.test',
            product_id: $this->productId,
            event_id: $this->eventId,
            send_confirmation_email: false,
            amount_paid: 30.00,
            locale: 'en',
            event_occurrence_id: $this->occurrenceId,
            seat_uid: self::SEAT,
        ));
    }
}
