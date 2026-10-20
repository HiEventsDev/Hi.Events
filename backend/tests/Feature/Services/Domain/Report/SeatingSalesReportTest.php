<?php

namespace Tests\Feature\Services\Domain\Report;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class SeatingSalesReportTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const ZONE = 'z90';

    private string $authToken;

    private int $firstDateId;

    private int $secondDateId;

    private int $cancelledDateId;

    private int $productId;

    private int $priceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));

        $this->firstDateId = $this->insertOccurrence(daysAhead: 1);
        $this->secondDateId = $this->insertOccurrence(daysAhead: 2);
        $this->cancelledDateId = $this->insertOccurrence(daysAhead: 3);
        DB::table('event_occurrences')->where('id', $this->cancelledDateId)->update(['status' => 'CANCELLED']);

        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null);

        $layout = $this->seatMapFixture('theatre');
        $layout['areas'][1]['elements'][] = [
            'id' => self::ZONE,
            'type' => 'zone',
            'label' => 'Standing',
            'band' => 'b_premium',
            'capacity' => 20,
            'x' => 0,
            'y' => 0,
            'w' => 100,
            'h' => 100,
            'rotation' => 0,
        ];
        $this->insertEventSeatMap($layout);

        $soldOrderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->charge($soldOrderId, $this->firstDateId, 'b_premium', 100);
        $this->claim($this->firstDateId, 'e2.0.0', 'b_premium', $soldOrderId);
        $this->charge($soldOrderId, $this->firstDateId, 'b_premium', 40);
        $this->claim($this->firstDateId, self::ZONE, 'b_premium', $soldOrderId, isZone: true);
        $this->claim($this->firstDateId, self::ZONE, 'b_premium', $soldOrderId, isZone: true);

        $movedOrderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->charge($movedOrderId, $this->firstDateId, 'b_premium', 50);
        $this->claim($this->firstDateId, 'e3.0.1', 'b_standard', $movedOrderId);

        $heldOrderId = $this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(10));
        $this->charge($heldOrderId, $this->firstDateId, 'b_premium', 70);
        $this->claim($this->firstDateId, 'e2.0.1', 'b_premium', $heldOrderId);
        $this->claim($this->firstDateId, 'e2.0.2', 'b_premium', $this->insertOrder(OrderStatus::RESERVED->name, now()->subMinute()));
        $this->claim($this->firstDateId, 'e2.0.3', 'b_premium', null);

        $standardOrderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->charge($standardOrderId, $this->secondDateId, 'b_standard', 30);
        $this->claim($this->secondDateId, 'e3.0.0', 'b_standard', $standardOrderId);

        $cancelledDateOrderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->charge($cancelledDateOrderId, $this->cancelledDateId, 'b_premium', 999);
        $this->claim($this->cancelledDateId, 'e2.0.5', 'b_premium', $cancelledDateOrderId);
        $this->claim($this->cancelledDateId, 'e2.0.6', 'b_premium', null);
    }

    public function test_all_dates_multiply_capacity_by_the_active_dates_and_split_areas_within_each_band(): void
    {
        $rows = $this->report();

        $this->assertRow($rows, 'b_premium', 'a1', capacity: 172, sold: 1, held: 1, blocked: 1, free: 169);
        $this->assertRow($rows, 'b_premium', 'a2', capacity: 40, sold: 2, held: 0, blocked: 0, free: 38);
        $this->assertRow($rows, 'b_premium', null, capacity: 212, sold: 3, held: 1, blocked: 1, free: 207, revenue: 190.0);
        $this->assertRow($rows, 'b_standard', 'a1', capacity: 482, sold: 2, held: 0, blocked: 0, free: 480);
        $this->assertRow($rows, 'b_standard', null, capacity: 482, sold: 2, held: 0, blocked: 0, free: 480, revenue: 30.0);
        $this->assertRow($rows, 'b_value', null, capacity: 150, sold: 0, held: 0, blocked: 0, free: 150, revenue: 0.0);

        $this->assertNull($this->row($rows, 'b_premium', 'a1')['total_gross']);
        $this->assertSame('Premium', $this->row($rows, 'b_premium', null)['band_name']);
        $this->assertSame('Stalls', $this->row($rows, 'b_premium', 'a1')['area_name']);
        $this->assertSame(
            ['b_premium:a1', 'b_premium:a2', 'b_premium:total', 'b_standard:a1', 'b_standard:total', 'b_value:a2', 'b_value:total'],
            $rows->map(fn (array $row) => $row['band_key'].':'.($row['area_id'] ?? 'total'))->all(),
        );
    }

    public function test_a_moved_seat_counts_in_its_current_band_while_revenue_stays_with_the_charged_band(): void
    {
        $rows = $this->report($this->firstDateId);

        $this->assertRow($rows, 'b_standard', null, capacity: 241, sold: 1, held: 0, blocked: 0, free: 240, revenue: 0.0);
        $this->assertRow($rows, 'b_premium', null, capacity: 106, sold: 3, held: 1, blocked: 1, free: 101, revenue: 190.0);
    }

    public function test_a_cancelled_date_reports_nothing(): void
    {
        $rows = $this->report($this->cancelledDateId);

        $this->assertRow($rows, 'b_premium', null, capacity: 0, sold: 0, held: 0, blocked: 0, free: 0, revenue: 0.0);
    }

    private function report(?int $occurrenceId = null): Collection
    {
        $query = $occurrenceId === null ? '' : "?occurrence_id=$occurrenceId";

        return collect($this->getJson("/events/{$this->eventId}/reports/seating_sales$query", ['Authorization' => 'Bearer '.$this->authToken])
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json());
    }

    private function row(Collection $rows, string $bandKey, ?string $areaId): array
    {
        return $rows->first(fn (array $row) => $row['band_key'] === $bandKey && $row['area_id'] === $areaId)
            ?? $this->fail("No row for $bandKey / ".($areaId ?? 'total'));
    }

    private function assertRow(Collection $rows, string $bandKey, ?string $areaId, int $capacity, int $sold, int $held, int $blocked, int $free, ?float $revenue = null): void
    {
        $row = $this->row($rows, $bandKey, $areaId);

        $this->assertSame(
            ['capacity' => $capacity, 'sold' => $sold, 'held' => $held, 'blocked' => $blocked, 'free' => $free],
            array_map('intval', array_intersect_key($row, array_flip(['capacity', 'sold', 'held', 'blocked', 'free']))),
        );

        if ($revenue !== null) {
            $this->assertEqualsWithDelta($revenue, (float) $row['total_gross'], 0.001);
        }
    }

    private function charge(int $orderId, int $occurrenceId, string $bandKey, float $totalGross): void
    {
        $itemId = $this->insertOrderItem($orderId, $this->productId, $this->priceId, $occurrenceId, 1);
        DB::table('order_items')->where('id', $itemId)->update(['band_key' => $bandKey, 'total_gross' => $totalGross]);
    }

    private function claim(int $occurrenceId, string $seatUid, string $bandKey, ?int $orderId, bool $isZone = false): void
    {
        DB::table('seat_claims')->insert([
            'event_id' => $this->eventId,
            'event_occurrence_id' => $occurrenceId,
            'seat_uid' => $seatUid,
            'is_zone' => $isZone,
            'band_key' => $bandKey,
            'seat_label' => $seatUid,
            'order_id' => $orderId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
