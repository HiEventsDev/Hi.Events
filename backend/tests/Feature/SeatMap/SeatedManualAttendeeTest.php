<?php

declare(strict_types=1);

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Exceptions\NoTicketsAvailableException;
use HiEvents\Services\Application\Handlers\Attendee\CreateAttendeeHandler;
use HiEvents\Services\Application\Handlers\Attendee\DTO\CreateAttendeeDTO;
use HiEvents\Services\Application\Handlers\Attendee\DTO\EditAttendeeDTO;
use HiEvents\Services\Application\Handlers\Attendee\EditAttendeeHandler;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class SeatedManualAttendeeTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const PREMIUM_SEAT = 'e2.0.0';

    private int $occurrenceId;

    private int $productId;

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
        $this->insertPrice($this->productId, null, price: 30.00);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), [
            'b_premium' => [$this->productId],
            'b_standard' => [$this->productId],
        ]);
    }

    public function test_the_order_item_records_the_band_of_the_seat_and_the_claim_points_at_it(): void
    {
        $attendee = $this->createAttendee();

        $item = DB::table('order_items')->where('order_id', $attendee->getOrderId())->first();
        $claim = DB::table('seat_claims')->where('order_id', $attendee->getOrderId())->first();

        $this->assertSame('b_premium', $item->band_key);
        $this->assertSame($item->id, $claim->order_item_id);
        $this->assertSame($attendee->getId(), $claim->attendee_id);
    }

    public function test_a_full_shared_pool_refuses_a_seated_attendee(): void
    {
        $this->insertCapacityAssignment(capacity: 3, usedCapacity: 3);

        $this->expectException(NoTicketsAvailableException::class);

        $this->createAttendee();
    }

    public function test_the_capacity_override_lets_a_seated_attendee_past_a_full_shared_pool(): void
    {
        $this->insertCapacityAssignment(capacity: 3, usedCapacity: 3);

        $attendee = $this->createAttendee(overrideCapacity: true);

        $this->assertSame(self::PREMIUM_SEAT, $attendee->getSeatUid());
    }

    public function test_a_held_back_seat_can_still_be_given_to_a_manual_attendee(): void
    {
        $this->insertCapacityAssignment(capacity: 3, usedCapacity: 0);
        DB::transaction(fn () => app(SeatClaimService::class)
            ->block($this->eventId, [$this->occurrenceId], [self::PREMIUM_SEAT], 'Held back'));

        $attendee = $this->createAttendee();

        $this->assertSame(self::PREMIUM_SEAT, $attendee->getSeatUid());
    }

    public function test_moving_a_seated_attendee_to_a_sold_out_price_is_refused(): void
    {
        $attendee = $this->createAttendee();
        [$otherProductId, $otherPriceId] = $this->otherSeatedProduct(initialQuantity: 1, quantitySold: 1);

        $this->expectException(NoTicketsAvailableException::class);

        $this->moveToProduct($attendee, $otherProductId, $otherPriceId);
    }

    public function test_moving_a_seated_attendee_into_a_full_shared_pool_is_refused(): void
    {
        $attendee = $this->createAttendee();
        [$otherProductId, $otherPriceId] = $this->otherSeatedProduct(initialQuantity: null);
        $this->insertCapacityAssignment(capacity: 1, usedCapacity: 1, productId: $otherProductId);

        $this->expectException(NoTicketsAvailableException::class);

        $this->moveToProduct($attendee, $otherProductId, $otherPriceId);
    }

    public function test_a_seated_attendee_keeps_their_seat_when_moved_to_a_price_with_room(): void
    {
        $attendee = $this->createAttendee();
        [$otherProductId, $otherPriceId] = $this->otherSeatedProduct(initialQuantity: 5);

        $this->moveToProduct($attendee, $otherProductId, $otherPriceId);

        $this->assertDatabaseHas('seat_claims', ['attendee_id' => $attendee->getId(), 'seat_uid' => self::PREMIUM_SEAT, 'product_price_id' => $otherPriceId]);
    }

    /**
     * @return array{int, int}
     */
    private function otherSeatedProduct(?int $initialQuantity, int $quantitySold = 0): array
    {
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, $initialQuantity, ProductQuantityAppliesTo::EVENT->name, $quantitySold, price: 30.00);
        DB::table('event_seat_map_band_products')->insert([
            'event_seat_map_id' => DB::table('event_seat_maps')->where('event_id', $this->eventId)->value('id'),
            'band_key' => 'b_premium',
            'product_id' => $productId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(SeatedProductLookupService::class)->forget();
        app(EventSeatMapLookupService::class)->forget($this->eventId);

        return [$productId, $priceId];
    }

    private function moveToProduct(AttendeeDomainObject $attendee, int $productId, int $priceId): void
    {
        app(EditAttendeeHandler::class)->handle(new EditAttendeeDTO(
            first_name: 'Walk',
            last_name: 'In',
            email: 'walkin@example.test',
            product_id: $productId,
            product_price_id: $priceId,
            event_id: $this->eventId,
            attendee_id: $attendee->getId(),
        ));
    }

    private function createAttendee(bool $overrideCapacity = false): AttendeeDomainObject
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
            override_capacity: $overrideCapacity,
            seat_uid: self::PREMIUM_SEAT,
        ));
    }

    private function insertCapacityAssignment(int $capacity, int $usedCapacity, ?int $productId = null): void
    {
        $capacityAssignmentId = DB::table('capacity_assignments')->insertGetId([
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
            'product_id' => $productId ?? $this->productId,
            'capacity_assignment_id' => $capacityAssignmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
