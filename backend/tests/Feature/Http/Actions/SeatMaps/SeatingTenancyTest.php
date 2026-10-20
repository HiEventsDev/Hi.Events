<?php

namespace Tests\Feature\Http\Actions\SeatMaps;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\ResponseCodes;
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

class SeatingTenancyTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;
    use ManagesFeatureFlags;

    private const SEAT = 'e2.0.9';

    private const OTHER_SEAT = 'e2.0.10';

    private string $authToken;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $siblingEventId;

    private int $siblingOccurrenceId;

    private int $siblingProductId;

    private int $siblingPriceId;

    private int $foreignEventId;

    private int $foreignOccurrenceId;

    private int $foreignSeatMapId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertLiveEvent();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, true);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));

        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct(priceType: 'FREE');
        $this->priceId = $this->insertPrice($this->productId, null);
        $eventSeatMapId = $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $this->updateEventSeatMap($eventSeatMapId, ['allow_seat_change' => true]);

        $this->buildSiblingEvent();
        $this->buildForeignAccountEvent();
    }

    public function test_a_public_seat_change_refuses_ids_that_belong_to_another_event_or_order(): void
    {
        $ticket = $this->createSeatedAttendee(self::SEAT)->json('data');
        $otherTicket = $this->createSeatedAttendee(self::OTHER_SEAT)->json('data');
        $orderShortId = $this->orderShortId($ticket['order_id']);
        $siblingOrderShortId = $this->orderShortId($this->siblingSeatedAttendee()['order_id']);

        $this->changeSeat($this->siblingEventId, $orderShortId, $ticket['short_id'], self::OTHER_SEAT)
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->changeSeat($this->eventId, $orderShortId, $otherTicket['short_id'], 'e2.0.11')
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->changeSeat($this->eventId, $siblingOrderShortId, $ticket['short_id'], 'e2.0.11')
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->assertDatabaseHas('attendees', ['id' => $ticket['id'], 'seat_uid' => self::SEAT]);
        $this->assertDatabaseHas('attendees', ['id' => $otherTicket['id'], 'seat_uid' => self::OTHER_SEAT]);

        $this->changeSeat($this->eventId, $orderShortId, $ticket['short_id'], 'e2.0.11')
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.seat_uid', 'e2.0.11');
    }

    public function test_a_public_seat_change_is_refused_when_self_service_is_disabled(): void
    {
        $ticket = $this->createSeatedAttendee(self::SEAT)->json('data');
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_attendee_self_edit' => false]);

        $this->changeSeat($this->eventId, $this->orderShortId($ticket['order_id']), $ticket['short_id'], 'e2.0.11')
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('attendees', ['id' => $ticket['id'], 'seat_uid' => self::SEAT]);
    }

    public function test_seat_lookups_on_a_hidden_event_are_not_found_before_any_seat_work(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::DRAFT->name]);
        DB::table('event_seat_maps')->where('event_id', $this->eventId)->update(['max_seats_per_order' => 2]);

        $this->bestAvailable($this->productId, 5)->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
        $this->bestAvailable(999999, 1)->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
        $this->getJson("/public/events/{$this->eventId}/occurrences/{$this->occurrenceId}/seat-availability")
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
    }

    public function test_an_organizer_cannot_move_an_attendee_that_belongs_to_another_event(): void
    {
        $siblingAttendee = $this->siblingSeatedAttendee();

        $this->putJson("/events/{$this->eventId}/attendees/{$siblingAttendee['id']}/seat", [
            'seat_uid' => self::OTHER_SEAT,
        ], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->assertDatabaseHas('attendees', ['id' => $siblingAttendee['id'], 'seat_uid' => self::SEAT]);
        $this->assertSame(0, DB::table('seat_claims')->where('event_occurrence_id', $this->occurrenceId)->count());
    }

    public function test_an_occurrence_from_another_event_is_rejected_by_every_occurrence_scoped_endpoint(): void
    {
        $this->getJson("/events/{$this->eventId}/occurrences/{$this->siblingOccurrenceId}/occupied-seats", $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->postJson("/events/{$this->eventId}/seat-blocks", [
            'event_occurrence_ids' => [$this->siblingOccurrenceId],
            'seat_uids' => [self::SEAT],
        ], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->postJson("/events/{$this->eventId}/seat-blocks/release", [
            'event_occurrence_ids' => [$this->siblingOccurrenceId],
            'seat_uids' => [self::SEAT],
        ], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->assertSame(0, DB::table('seat_claims')->where('event_occurrence_id', $this->siblingOccurrenceId)->count());
    }

    public function test_every_event_scoped_seating_endpoint_refuses_another_accounts_event(): void
    {
        $url = "/events/{$this->foreignEventId}/seat-map";
        $requests = [
            fn () => $this->getJson($url, $this->authHeaders()),
            fn () => $this->putJson("$url/layout", ['layout' => $this->seatMapFixture('conference')], $this->authHeaders()),
            fn () => $this->putJson("$url/band-products", ['band_products' => []], $this->authHeaders()),
            fn () => $this->putJson("$url/rules", [
                'prevent_orphan_seats' => true,
                'max_seats_per_order' => 4,
                'allow_seat_change' => true,
            ], $this->authHeaders()),
            fn () => $this->postJson("/events/{$this->foreignEventId}/seat-blocks", [
                'event_occurrence_ids' => [$this->foreignOccurrenceId],
                'seat_uids' => [self::SEAT],
            ], $this->authHeaders()),
            fn () => $this->postJson("/events/{$this->foreignEventId}/seat-blocks/release", [
                'event_occurrence_ids' => [$this->foreignOccurrenceId],
                'seat_uids' => [self::SEAT],
            ], $this->authHeaders()),
            fn () => $this->getJson("/events/{$this->foreignEventId}/occurrences/{$this->foreignOccurrenceId}/occupied-seats", $this->authHeaders()),
            fn () => $this->putJson("/events/{$this->foreignEventId}/attendees/{$this->foreignAttendeeId()}/seat", [
                'seat_uid' => self::OTHER_SEAT,
            ], $this->authHeaders()),
            fn () => $this->postJson($url, ['seat_map_id' => $this->ownSeatMapId()], $this->authHeaders()),
            fn () => $this->postJson("$url/sync-from-source", ['dry_run' => true], $this->authHeaders()),
            fn () => $this->deleteJson($url, [], $this->authHeaders()),
        ];

        foreach ($requests as $index => $request) {
            $request()->assertStatus(ResponseCodes::HTTP_FORBIDDEN, "Request $index must be forbidden");
        }

        $foreignSeatMap = DB::table('event_seat_maps')->where('event_id', $this->foreignEventId)->sole();
        $this->assertSame(1, $foreignSeatMap->version);
        $this->assertFalse($foreignSeatMap->prevent_orphan_seats);
        $this->assertSame(1, DB::table('event_seat_map_band_products')->where('event_seat_map_id', $foreignSeatMap->id)->count());
        $this->assertSame(0, DB::table('seat_claims')->where('event_occurrence_id', $this->foreignOccurrenceId)->count());
        $this->assertSame([self::SEAT], DB::table('attendees')->where('event_id', $this->foreignEventId)->pluck('seat_uid')->all());
    }

    public function test_another_accounts_venue_map_cannot_be_attached(): void
    {
        $eventWithoutMap = $this->insertLiveEvent();

        $this->postJson("/events/$eventWithoutMap/seat-map", ['seat_map_id' => $this->foreignSeatMapId], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->assertSame(0, DB::table('event_seat_maps')->where('event_id', $eventWithoutMap)->count());
    }

    public function test_best_available_refuses_a_ticket_from_another_event_sharing_the_same_venue_map(): void
    {
        $this->bestAvailable($this->siblingProductId, 2)
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->bestAvailable($this->productId, 2)
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(2, 'data.seat_uids');
    }

    private function foreignAttendeeId(): int
    {
        $orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->foreignEventId,
            'currency' => 'USD',
            'status' => 'COMPLETED',
            'public_id' => 'PUB_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('attendees')->insertGetId([
            'order_id' => $orderId,
            'product_id' => DB::table('products')->where('event_id', $this->foreignEventId)->value('id'),
            'product_price_id' => $this->priceId,
            'short_id' => 'att_'.uniqid(),
            'email' => 'foreign+'.uniqid().'@example.test',
            'event_id' => $this->foreignEventId,
            'event_occurrence_id' => $this->foreignOccurrenceId,
            'public_id' => 'ATT_'.uniqid(),
            'status' => 'ACTIVE',
            'seat_uid' => self::SEAT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ownSeatMapId(): int
    {
        return $this->insertLibrarySeatMap($this->accountId, $this->organizerId);
    }

    private function insertLibrarySeatMap(int $accountId, int $organizerId): int
    {
        return DB::table('seat_maps')->insertGetId([
            'account_id' => $accountId,
            'organizer_id' => $organizerId,
            'name' => 'Theatre',
            'layout' => json_encode($this->seatMapFixture('theatre')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bestAvailable(int $productId, int $quantity): TestResponse
    {
        return $this->getJson(
            "/public/events/{$this->eventId}/occurrences/{$this->occurrenceId}/best-available-seats"
            ."?product_id=$productId&quantity=$quantity"
        );
    }

    private function changeSeat(int $eventId, string $orderShortId, string $attendeeShortId, string $seatUid): TestResponse
    {
        return $this->putJson(
            "/public/events/$eventId/order/$orderShortId/attendees/$attendeeShortId/seat",
            ['seat_uid' => $seatUid],
        );
    }

    private function siblingSeatedAttendee(): array
    {
        $eventId = $this->eventId;
        $occurrenceId = $this->occurrenceId;
        $productId = $this->productId;
        $priceId = $this->priceId;

        $this->eventId = $this->siblingEventId;
        $this->occurrenceId = $this->siblingOccurrenceId;
        $this->productId = $this->siblingProductId;
        $this->priceId = $this->siblingPriceId;

        $attendee = $this->createSeatedAttendee(self::SEAT)
            ->assertStatus(ResponseCodes::HTTP_CREATED)
            ->json('data');

        $this->eventId = $eventId;
        $this->occurrenceId = $occurrenceId;
        $this->productId = $productId;
        $this->priceId = $priceId;

        return $attendee;
    }

    private function orderShortId(int $orderId): string
    {
        return DB::table('orders')->where('id', $orderId)->value('short_id');
    }

    private function createSeatedAttendee(string $seatUid): TestResponse
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

    private function insertLiveEvent(): int
    {
        $eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_settings')->insert(['event_id' => $eventId, 'created_at' => now(), 'updated_at' => now()]);

        return $eventId;
    }

    private function buildSiblingEvent(): void
    {
        $primaryEventId = $this->eventId;

        $this->eventId = $this->insertLiveEvent();
        $this->siblingEventId = $this->eventId;
        $this->siblingOccurrenceId = $this->insertOccurrence();
        $this->siblingProductId = $this->insertProduct(priceType: 'FREE');
        $this->siblingPriceId = $this->insertPrice($this->siblingProductId, null);
        $siblingSeatMapId = $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->siblingProductId]]);
        $this->updateEventSeatMap($siblingSeatMapId, ['allow_seat_change' => true]);

        $this->eventId = $primaryEventId;
    }

    private function buildForeignAccountEvent(): void
    {
        $primaryEventId = $this->eventId;
        $primaryAccountId = $this->accountId;
        $primaryUserId = $this->userId;
        $primaryOrganizerId = $this->organizerId;

        $this->insertAccountAndOrganizer();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, true);
        $this->eventId = $this->insertLiveEvent();
        $this->foreignEventId = $this->eventId;
        $this->foreignOccurrenceId = $this->insertOccurrence();
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->insertProduct(priceType: 'FREE')]]);
        $this->foreignSeatMapId = $this->insertLibrarySeatMap($this->accountId, $this->organizerId);

        $this->eventId = $primaryEventId;
        $this->accountId = $primaryAccountId;
        $this->userId = $primaryUserId;
        $this->organizerId = $primaryOrganizerId;
    }

    private function authHeaders(): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
