<?php

namespace Tests\Feature\Http\Actions\SeatMaps;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Services\Domain\SeatBlockDatesResolver;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class MultiDateSeatBlocksTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;
    use ManagesFeatureFlags;

    private const SEAT = 'e2.0.9';

    private const OTHER_SEAT = 'e2.0.10';

    private string $authToken;

    private int $firstDateId;

    private int $secondDateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, true);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));

        $this->firstDateId = $this->insertOccurrence(daysAhead: 1);
        $this->secondDateId = $this->insertOccurrence(daysAhead: 2);
        $productId = $this->insertProduct(priceType: 'FREE');
        $this->insertPrice($productId, null);
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$productId]]);
    }

    public function test_a_seat_sold_on_one_date_is_skipped_and_reported_while_the_rest_are_blocked(): void
    {
        $this->sellSeat($this->secondDateId, self::SEAT);

        $this->block(['event_occurrence_ids' => [$this->firstDateId, $this->secondDateId]], [self::SEAT, self::OTHER_SEAT])
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.blocked', 3)
            ->assertJsonPath('data.skipped', [['event_occurrence_id' => $this->secondDateId, 'seat_uids' => [self::SEAT]]]);

        $this->assertEqualsCanonicalizing(
            ["{$this->firstDateId}:".self::SEAT, "{$this->firstDateId}:".self::OTHER_SEAT, "{$this->secondDateId}:".self::OTHER_SEAT],
            $this->blocks()->map(fn (object $block) => "$block->event_occurrence_id:$block->seat_uid")->all(),
        );
    }

    public function test_all_upcoming_dates_skips_cancelled_deleted_and_past_dates(): void
    {
        $cancelledDateId = $this->insertOccurrence(daysAhead: 3);
        DB::table('event_occurrences')->where('id', $cancelledDateId)->update(['status' => 'CANCELLED']);
        $deletedDateId = $this->insertOccurrence(daysAhead: 4);
        DB::table('event_occurrences')->where('id', $deletedDateId)->update(['deleted_at' => now()]);
        $this->insertOccurrence(daysAhead: -1);

        $this->block(['all_upcoming_dates' => true], [self::SEAT])
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.blocked', 2);

        $this->assertEqualsCanonicalizing(
            [$this->firstDateId, $this->secondDateId],
            $this->blocks()->pluck('event_occurrence_id')->all(),
        );
    }

    public function test_a_date_from_another_event_is_not_found_and_nothing_is_blocked(): void
    {
        $ownEventId = $this->eventId;
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        $otherEventDateId = $this->insertOccurrence();
        $this->eventId = $ownEventId;

        $this->block(['event_occurrence_ids' => [$this->firstDateId, $otherEventDateId]], [self::SEAT])
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
        $this->release(['event_occurrence_ids' => [$otherEventDateId]], [self::SEAT])
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->assertCount(0, $this->blocks());
    }

    public function test_more_seats_across_dates_than_the_cap_is_refused(): void
    {
        $seatCount = 500;
        $dateCount = intdiv(SeatBlockDatesResolver::MAX_SEATS_ACROSS_DATES, $seatCount) + 1;
        $dateIds = [$this->firstDateId, $this->secondDateId];
        while (count($dateIds) < $dateCount) {
            $dateIds[] = $this->insertOccurrence(daysAhead: count($dateIds) + 1);
        }
        $seatUids = array_map(fn (int $seat) => "e3.0.$seat", range(0, $seatCount - 1));

        $this->block(['event_occurrence_ids' => $dateIds], $seatUids)
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('seat_uids');

        $this->assertCount(0, $this->blocks());
    }

    public function test_repeated_seats_and_dates_are_blocked_once(): void
    {
        $this->block(['event_occurrence_ids' => [$this->firstDateId, $this->firstDateId]], [self::SEAT, self::SEAT])
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.blocked', 1)
            ->assertJsonPath('data.skipped', []);

        $this->assertCount(1, $this->blocks());
    }

    public function test_an_oversized_seat_list_is_refused_without_slow_validation(): void
    {
        $seatUids = array_map(fn (int $seat) => "e9.$seat.1", range(0, 39_999));

        $startedAt = microtime(true);
        $this->release(['event_occurrence_ids' => [$this->firstDateId]], $seatUids)
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('seat_uids');

        $this->assertLessThan(3, microtime(true) - $startedAt);
    }

    public function test_release_frees_the_seats_on_every_chosen_date_and_leaves_sales_alone(): void
    {
        $thirdDateId = $this->insertOccurrence(daysAhead: 3);
        $this->block(['all_upcoming_dates' => true], [self::SEAT, self::OTHER_SEAT]);
        $this->sellSeat($thirdDateId, 'e2.0.11');

        $this->release(['event_occurrence_ids' => [$this->firstDateId, $this->secondDateId]], [self::SEAT, self::OTHER_SEAT, 'e2.0.11'])
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.released', 4);

        $this->assertSame([$thirdDateId, $thirdDateId], $this->blocks()->pluck('event_occurrence_id')->all());

        $this->release(['all_upcoming_dates' => true], [self::SEAT, self::OTHER_SEAT, 'e2.0.11'])
            ->assertJsonPath('data.released', 2);

        $this->assertCount(0, $this->blocks());
        $this->assertSame(1, DB::table('seat_claims')->where('event_occurrence_id', $thirdDateId)->count());
    }

    public function test_blocking_an_already_held_back_seat_replaces_its_reason(): void
    {
        $this->block(['all_upcoming_dates' => true], [self::SEAT], 'Sound desk');

        $this->block(['all_upcoming_dates' => true], [self::SEAT], 'Camera platform')
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.blocked', 2)
            ->assertJsonPath('data.skipped', []);

        $this->assertSame(['Camera platform', 'Camera platform'], $this->blocks()->pluck('block_reason')->all());
    }

    public function test_the_reason_is_stored_as_plain_text_and_a_blank_one_as_none(): void
    {
        $this->block(['event_occurrence_ids' => [$this->firstDateId]], [self::SEAT], '  <b>Sound</b> desk<script>x</script> ')
            ->assertStatus(ResponseCodes::HTTP_OK);
        $this->block(['event_occurrence_ids' => [$this->firstDateId]], [self::OTHER_SEAT], '   ')
            ->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertEquals([self::SEAT => 'Sound deskx', self::OTHER_SEAT => null], $this->blocks()->pluck('block_reason', 'seat_uid')->all());
    }

    public function test_dates_are_either_listed_or_all_upcoming(): void
    {
        $this->block([], [self::SEAT])->assertJsonValidationErrors('event_occurrence_ids');
        $this->block(['all_upcoming_dates' => true, 'event_occurrence_ids' => [$this->firstDateId]], [self::SEAT])
            ->assertJsonValidationErrors('event_occurrence_ids');
    }

    private function sellSeat(int $occurrenceId, string $seatUid): void
    {
        DB::table('seat_claims')->insert([
            'event_id' => $this->eventId,
            'event_occurrence_id' => $occurrenceId,
            'seat_uid' => $seatUid,
            'is_zone' => false,
            'band_key' => 'b_premium',
            'seat_label' => 'Stalls · '.$seatUid,
            'order_id' => $this->insertOrder(OrderStatus::COMPLETED->name),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function blocks(): Collection
    {
        return DB::table('seat_claims')
            ->where('event_id', $this->eventId)
            ->whereNull('order_id')
            ->orderBy('event_occurrence_id')
            ->get();
    }

    private function block(array $dates, array $seatUids, ?string $reason = null): TestResponse
    {
        return $this->postJson("/events/{$this->eventId}/seat-blocks", [
            ...$dates,
            'seat_uids' => $seatUids,
            'reason' => $reason,
        ], $this->authHeaders());
    }

    private function release(array $dates, array $seatUids): TestResponse
    {
        return $this->postJson("/events/{$this->eventId}/seat-blocks/release", [
            ...$dates,
            'seat_uids' => $seatUids,
        ], $this->authHeaders());
    }

    private function authHeaders(): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
