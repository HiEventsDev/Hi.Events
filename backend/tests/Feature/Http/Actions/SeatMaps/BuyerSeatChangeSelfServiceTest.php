<?php

namespace Tests\Feature\Http\Actions\SeatMaps;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class BuyerSeatChangeSelfServiceTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;
    use ManagesFeatureFlags;

    private const SEAT = 'e2.0.9';

    private const OTHER_PREMIUM_SEAT = 'e2.0.10';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

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

        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct(priceType: 'FREE');
        $this->priceId = $this->insertPrice($this->productId, null);

        $eventSeatMapId = $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_premium' => [$this->productId]]);
        $this->updateEventSeatMap($eventSeatMapId, ['allow_seat_change' => true]);
    }

    public function test_a_buyer_cannot_change_seat_when_self_service_is_off(): void
    {
        $attendee = $this->postJson("/events/{$this->eventId}/attendees", [
            'product_id' => $this->productId,
            'product_price_id' => $this->priceId,
            'event_occurrence_id' => $this->occurrenceId,
            'seat_uid' => self::SEAT,
            'email' => 'guest+'.uniqid().'@example.test',
            'first_name' => 'Ada',
            'last_name' => 'Guest',
            'amount_paid' => 0,
            'send_confirmation_email' => false,
            'locale' => 'en',
        ], ['Authorization' => 'Bearer '.JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId))])
            ->assertStatus(ResponseCodes::HTTP_CREATED)
            ->json('data');
        $orderShortId = DB::table('orders')->where('id', $attendee['order_id'])->value('short_id');

        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_attendee_self_edit' => false]);

        $this->putJson(
            "/public/events/{$this->eventId}/order/$orderShortId/attendees/{$attendee['short_id']}/seat",
            ['seat_uid' => self::OTHER_PREMIUM_SEAT],
        )->assertStatus(ResponseCodes::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('attendees', ['id' => $attendee['id'], 'seat_uid' => self::SEAT]);
    }
}
