<?php

namespace Tests\Feature\Http\Actions\SeatMaps;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mail\Attendee\AttendeeTicketMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class SeatOperationsTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;
    use ManagesFeatureFlags;

    private const SEAT = 'e2.0.9';

    private const OTHER_PREMIUM_SEAT = 'e2.0.10';

    private string $authToken;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $eventSeatMapId;

    private string $standardSeat;

    protected function setUp(): void
    {
        parent::setUp();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, true);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));

        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct(priceType: 'FREE');
        $this->priceId = $this->insertPrice($this->productId, null);

        $layout = $this->seatMapFixture('theatre');
        $this->eventSeatMapId = $this->insertEventSeatMap($layout, ['b_premium' => [$this->productId]]);
        $this->standardSeat = collect($layout['areas'][0]['elements'])
            ->flatMap(fn (array $element) => $element['seats'] ?? [])
            ->firstWhere('band', 'b_standard')['uid'];
    }

    public function test_blocked_seats_are_unavailable_until_released(): void
    {
        $this->block([self::SEAT], 'Sound desk')->assertStatus(ResponseCodes::HTTP_OK);
        $this->block([self::SEAT, self::OTHER_PREMIUM_SEAT], 'Sound desk')
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.blocked', 2)
            ->assertJsonPath('data.skipped', []);

        $this->getJson("/events/{$this->eventId}/occurrences/{$this->occurrenceId}/occupied-seats", $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'BLOCKED')
            ->assertJsonPath('data.0.block_reason', 'Sound desk')
            ->assertJsonPath('data.0.seat_label', 'Stalls · A-10');

        $this->getJson("/public/events/{$this->eventId}/occurrences/{$this->occurrenceId}/seat-availability")
            ->assertJsonPath('data.unavailable_seat_uids', [self::SEAT, self::OTHER_PREMIUM_SEAT]);

        $this->postJson("/events/{$this->eventId}/seat-blocks/release", [
            'event_occurrence_ids' => [$this->occurrenceId],
            'seat_uids' => [self::SEAT, self::OTHER_PREMIUM_SEAT],
        ], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK)->assertJsonPath('data.released', 2);

        $this->assertSame(0, DB::table('seat_claims')->where('event_occurrence_id', $this->occurrenceId)->count());
    }

    public function test_a_sold_seat_is_skipped_and_the_free_seats_are_blocked(): void
    {
        $this->createSeatedAttendee(self::SEAT);

        $this->block([self::SEAT, self::OTHER_PREMIUM_SEAT])
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.blocked', 1)
            ->assertJsonPath('data.skipped', [['event_occurrence_id' => $this->occurrenceId, 'seat_uids' => [self::SEAT]]]);

        $this->assertSame(2, DB::table('seat_claims')->where('event_occurrence_id', $this->occurrenceId)->count());
    }

    public function test_zones_and_unknown_seats_cannot_be_blocked(): void
    {
        $this->block(['e99.0.0'])->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_an_organizer_adds_an_attendee_to_a_blocked_seat(): void
    {
        $this->block([self::SEAT])->assertStatus(ResponseCodes::HTTP_OK);

        $this->createSeatedAttendee(self::SEAT)
            ->assertStatus(ResponseCodes::HTTP_CREATED)
            ->assertJsonPath('data.seat_uid', self::SEAT)
            ->assertJsonPath('data.seat_label', 'Stalls · A-10');

        $claim = DB::table('seat_claims')->where('event_occurrence_id', $this->occurrenceId)->sole();
        $this->assertNotNull($claim->order_id);
        $this->assertNotNull($claim->attendee_id);
    }

    public function test_a_seated_ticket_needs_a_seat_from_a_linked_band(): void
    {
        $this->createSeatedAttendee(null)->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)->assertJsonValidationErrors('seat_uid');
        $this->createSeatedAttendee($this->standardSeat)->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)->assertJsonValidationErrors('seat_uid');
        $this->createSeatedAttendee(self::SEAT)->assertStatus(ResponseCodes::HTTP_CREATED);
        $this->createSeatedAttendee(self::SEAT)->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)->assertJsonValidationErrors('seat_uid');
    }

    public function test_an_organizer_moves_an_attendee_onto_a_blocked_seat_but_not_a_sold_one(): void
    {
        Mail::fake();
        $attendeeId = $this->createSeatedAttendee(self::SEAT)->json('data.id');
        $this->createSeatedAttendee('e2.0.11');
        $this->block([self::OTHER_PREMIUM_SEAT]);

        $this->moveSeat($attendeeId, 'e2.0.11')->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $this->moveSeat($attendeeId, $this->standardSeat)->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);

        $this->moveSeat($attendeeId, self::OTHER_PREMIUM_SEAT)
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.seat_uid', self::OTHER_PREMIUM_SEAT);

        $this->assertEqualsCanonicalizing(
            [self::OTHER_PREMIUM_SEAT, 'e2.0.11'],
            DB::table('seat_claims')->where('event_occurrence_id', $this->occurrenceId)->pluck('seat_uid')->all(),
        );
        $this->assertDatabaseHas('order_audit_logs', ['attendee_id' => $attendeeId, 'changed_fields' => 'seat_label']);
        Mail::assertQueued(AttendeeTicketMail::class, 1);
    }

    public function test_an_organizer_move_across_bands_records_the_band_change(): void
    {
        DB::table('event_seat_map_band_products')->insert([
            'event_seat_map_id' => $this->eventSeatMapId,
            'band_key' => 'b_standard',
            'product_id' => $this->productId,
            'price_adjustment' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $attendeeId = $this->createSeatedAttendee(self::SEAT)->json('data.id');

        $this->moveSeat($attendeeId, $this->standardSeat)->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertDatabaseHas('order_audit_logs', [
            'attendee_id' => $attendeeId,
            'changed_fields' => 'seat_label,band_key',
        ]);
    }

    public function test_attendees_can_be_found_by_seat(): void
    {
        $attendeeId = $this->createSeatedAttendee(self::SEAT)->json('data.id');
        $this->createSeatedAttendee(self::OTHER_PREMIUM_SEAT);

        $this->getJson("/events/{$this->eventId}/attendees?query=A-10", $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attendeeId);
    }

    public function test_cancelled_attendees_and_attendees_on_cancelled_dates_cannot_be_moved(): void
    {
        $attendeeId = $this->createSeatedAttendee(self::SEAT)->json('data.id');

        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['status' => 'CANCELLED']);
        $this->moveSeat($attendeeId, self::OTHER_PREMIUM_SEAT)->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);

        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['status' => 'ACTIVE']);
        DB::table('attendees')->where('id', $attendeeId)->update(['status' => 'CANCELLED']);
        $this->moveSeat($attendeeId, self::OTHER_PREMIUM_SEAT)->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_a_buyer_changes_seat_within_the_band_only_when_the_event_allows_it(): void
    {
        $attendee = $this->createSeatedAttendee(self::SEAT)->json('data');
        $orderShortId = DB::table('orders')->where('id', $attendee['order_id'])->value('short_id');
        $url = "/public/events/{$this->eventId}/order/$orderShortId/attendees/{$attendee['short_id']}/seat";
        $this->block(['e2.0.11']);

        $this->putJson($url, ['seat_uid' => self::OTHER_PREMIUM_SEAT])->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);

        $this->updateEventSeatMap($this->eventSeatMapId, ['allow_seat_change' => true]);

        $this->putJson($url, ['seat_uid' => 'e2.0.11'])->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $this->putJson($url, ['seat_uid' => $this->standardSeat])->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        $this->putJson($url, ['seat_uid' => self::OTHER_PREMIUM_SEAT])
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.seat_label', 'Stalls · A-11');

        DB::table('orders')->where('id', $attendee['order_id'])->update(['status' => OrderStatus::CANCELLED->name]);
        $this->putJson($url, ['seat_uid' => self::SEAT])->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_a_buyer_cannot_change_seat_once_the_date_has_started(): void
    {
        $attendee = $this->createSeatedAttendee(self::SEAT)->json('data');
        $orderShortId = DB::table('orders')->where('id', $attendee['order_id'])->value('short_id');
        $url = "/public/events/{$this->eventId}/order/$orderShortId/attendees/{$attendee['short_id']}/seat";
        $this->updateEventSeatMap($this->eventSeatMapId, ['allow_seat_change' => true]);

        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update([
            'start_date' => now()->subMinutes(30)->toDateTimeString(),
            'end_date' => now()->addHours(2)->toDateTimeString(),
        ]);

        $this->putJson($url, ['seat_uid' => self::OTHER_PREMIUM_SEAT])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.seat_uid.0', 'Seats can no longer be changed for this ticket');
        $this->assertDatabaseHas('attendees', ['id' => $attendee['id'], 'seat_uid' => self::SEAT]);
    }

    public function test_a_buyer_cannot_change_seat_after_being_checked_in(): void
    {
        $attendee = $this->createSeatedAttendee(self::SEAT)->json('data');
        $orderShortId = DB::table('orders')->where('id', $attendee['order_id'])->value('short_id');
        $url = "/public/events/{$this->eventId}/order/$orderShortId/attendees/{$attendee['short_id']}/seat";
        $this->updateEventSeatMap($this->eventSeatMapId, ['allow_seat_change' => true]);

        $this->checkIn($attendee['id']);

        $this->putJson($url, ['seat_uid' => self::OTHER_PREMIUM_SEAT])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertDatabaseHas('attendees', ['id' => $attendee['id'], 'seat_uid' => self::SEAT]);
    }

    private function checkIn(int $attendeeId): void
    {
        $checkInListId = DB::table('check_in_lists')->insertGetId([
            'short_id' => 'cil_'.uniqid(),
            'name' => 'Door',
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendee_check_ins')->insert([
            'short_id' => 'aci_'.uniqid(),
            'check_in_list_id' => $checkInListId,
            'product_id' => $this->productId,
            'attendee_id' => $attendeeId,
            'event_id' => $this->eventId,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function block(array $seatUids, ?string $reason = null): TestResponse
    {
        return $this->postJson("/events/{$this->eventId}/seat-blocks", [
            'event_occurrence_ids' => [$this->occurrenceId],
            'seat_uids' => $seatUids,
            'reason' => $reason,
        ], $this->authHeaders());
    }

    private function moveSeat(int $attendeeId, string $seatUid): TestResponse
    {
        return $this->putJson("/events/{$this->eventId}/attendees/$attendeeId/seat", ['seat_uid' => $seatUid], $this->authHeaders());
    }

    private function createSeatedAttendee(?string $seatUid): TestResponse
    {
        return $this->postJson("/events/{$this->eventId}/attendees", [
            'product_id' => $this->productId,
            'product_price_id' => $this->priceId,
            'event_occurrence_id' => $this->occurrenceId,
            'seat_uid' => $seatUid,
            'email' => 'guest+'.uniqid().'@example.test',
            'first_name' => 'Ada',
            'last_name' => 'Guest',
            'amount_paid' => 0,
            'send_confirmation_email' => false,
            'locale' => 'en',
        ], $this->authHeaders());
    }

    private function authHeaders(): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
