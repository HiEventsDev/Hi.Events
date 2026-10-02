<?php

declare(strict_types=1);

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DetachEventSeatMapHandler;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLayoutUpdater;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapIndex;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class EventSeatMapClaimGuardsTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
    }

    public function test_renaming_an_area_relabels_tens_of_thousands_of_sold_seats(): void
    {
        $layout = $this->seatMapFixture('theatre');
        $this->insertEventSeatMap($layout);
        $pastOccurrenceId = $this->insertOccurrence(daysAhead: -10);
        $this->insertOccurrences(110);
        $orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $stalls = collect(SeatMapIndex::fromLayout($layout)->seats())
            ->filter(fn (array $seat) => str_starts_with($seat['label'], 'Stalls'))
            ->map(fn (array $seat, string $uid) => ['seat_uid' => $uid, 'band_key' => $seat['band'], 'seat_label' => $seat['label']])
            ->values()
            ->all();
        $this->insertClaimsOnEveryDate($orderId, $stalls);
        $this->assertGreaterThan(33_000, DB::table('seat_claims')->count());
        $upcomingClaim = DB::table('seat_claims')->where('event_occurrence_id', '!=', $pastOccurrenceId)->first();
        $pastClaim = DB::table('seat_claims')->where('event_occurrence_id', $pastOccurrenceId)->first();
        $upcomingAttendeeId = $this->insertAttendeeFor($upcomingClaim);
        $pastAttendeeId = $this->insertAttendeeFor($pastClaim);

        $renamed = $layout;
        $renamed['areas'][0]['name'] = 'Floor';
        app(EventSeatMapLayoutUpdater::class)->replaceLayout(
            app(EventSeatMapLookupService::class)->getForEvent($this->eventId),
            $renamed,
            allowRelabel: true,
        );

        $this->assertSame(0, DB::table('seat_claims')->where('event_occurrence_id', '!=', $pastOccurrenceId)->where('seat_label', 'like', 'Stalls%')->count());
        $this->assertStringStartsWith('Floor · ', DB::table('attendees')->where('id', $upcomingAttendeeId)->value('seat_label'));
        $this->assertStringStartsWith('Stalls · ', DB::table('attendees')->where('id', $pastAttendeeId)->value('seat_label'));
    }

    public function test_a_zone_cannot_shrink_below_the_busiest_upcoming_date(): void
    {
        $layout = $this->seatMapFixture('club');
        $this->insertEventSeatMap($layout);
        $quietDate = $this->insertOccurrence();
        $busyDate = $this->insertOccurrence(daysAhead: 2);
        $orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $zone = ['seat_uid' => 'z2', 'band_key' => 'b_premium', 'seat_label' => SeatMapIndex::fromLayout($layout)->labelOf('z2')];
        $this->insertZoneClaims($orderId, $quietDate, $zone, 1);
        $this->insertZoneClaims($orderId, $busyDate, $zone, 3);

        $shrunk = $layout;
        $shrunk['areas'][0]['elements'][1]['capacity'] = 2;

        $this->expectException(SeatMapChangeConflictException::class);
        $this->expectExceptionMessage('new capacity allows');

        app(EventSeatMapLayoutUpdater::class)->replaceLayout(app(EventSeatMapLookupService::class)->getForEvent($this->eventId), $shrunk);
    }

    public function test_detaching_removes_holds_on_past_and_cancelled_dates_and_dead_claims(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'));
        $pastDate = $this->insertOccurrence(daysAhead: -10);
        $cancelledDate = $this->insertOccurrence(daysAhead: 3);
        $upcomingDate = $this->insertOccurrence(daysAhead: 4);
        app(SeatClaimService::class)->block($this->eventId, [$pastDate, $cancelledDate], ['e2.0.0'], 'Old hold');
        DB::table('event_occurrences')->where('id', $cancelledDate)->update(['status' => 'CANCELLED']);
        $abandoned = $this->insertOrder(OrderStatus::ABANDONED->name);
        $pastSale = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->insertZoneClaims($abandoned, $upcomingDate, ['seat_uid' => 'e2.0.1', 'band_key' => 'b_premium', 'seat_label' => 'A-2'], 1, isZone: false);
        $this->insertZoneClaims($pastSale, $pastDate, ['seat_uid' => 'e2.0.2', 'band_key' => 'b_premium', 'seat_label' => 'A-3'], 1, isZone: false);

        app(DetachEventSeatMapHandler::class)->handle($this->eventId);

        $this->assertSame([$pastSale], DB::table('seat_claims')->where('event_id', $this->eventId)->pluck('order_id')->all());
    }

    public function test_detaching_is_refused_while_an_upcoming_date_has_a_hold(): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'));
        $upcomingDate = $this->insertOccurrence();
        app(SeatClaimService::class)->block($this->eventId, [$upcomingDate], ['e2.0.0'], null);

        $this->expectException(ResourceConflictException::class);

        app(DetachEventSeatMapHandler::class)->handle($this->eventId);
    }

    private function insertOccurrences(int $count): void
    {
        DB::statement(
            "INSERT INTO event_occurrences (short_id, event_id, start_date, end_date, status, used_capacity, is_overridden, created_at, updated_at)
             SELECT 'occ_' || g || '_' || ?, ?, ?::timestamp + (g || ' days')::interval, ?::timestamp + (g || ' days')::interval, 'ACTIVE', 0, false, ?, ?
             FROM generate_series(1, ?) AS g",
            [uniqid(), $this->eventId, now(), now()->addHours(2), now(), now(), $count],
        );
    }

    private function insertClaimsOnEveryDate(int $orderId, array $seats): void
    {
        DB::statement(
            'INSERT INTO seat_claims (event_id, event_occurrence_id, seat_uid, is_zone, band_key, seat_label, order_id, created_at, updated_at)
             SELECT ?, occurrence.id, seat.seat_uid, false, seat.band_key, seat.seat_label, ?, ?, ?
             FROM event_occurrences AS occurrence
             CROSS JOIN jsonb_to_recordset(?::jsonb) AS seat(seat_uid varchar, band_key varchar, seat_label varchar)
             WHERE occurrence.event_id = ?',
            [$this->eventId, $orderId, now(), now(), json_encode($seats), $this->eventId],
        );
    }

    private function insertZoneClaims(int $orderId, int $occurrenceId, array $seat, int $count, bool $isZone = true): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('seat_claims')->insert([
                'event_id' => $this->eventId,
                'event_occurrence_id' => $occurrenceId,
                'is_zone' => $isZone,
                'order_id' => $orderId,
                'created_at' => now(),
                'updated_at' => now(),
                ...$seat,
            ]);
        }
    }

    private function insertAttendeeFor(object $claim): int
    {
        $attendeeId = DB::table('attendees')->insertGetId([
            'short_id' => 'att_'.uniqid(),
            'email' => 'attendee+'.uniqid().'@example.test',
            'order_id' => $claim->order_id,
            'product_id' => $productId = $this->insertProduct(),
            'product_price_id' => $this->insertPrice($productId, null),
            'event_id' => $this->eventId,
            'event_occurrence_id' => $claim->event_occurrence_id,
            'public_id' => 'ATT_'.uniqid(),
            'status' => 'ACTIVE',
            'seat_uid' => $claim->seat_uid,
            'seat_label' => $claim->seat_label,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('seat_claims')->where('id', $claim->id)->update(['attendee_id' => $attendeeId]);

        return $attendeeId;
    }
}
