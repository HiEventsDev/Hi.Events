<?php

declare(strict_types=1);

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Services\Domain\SeatSelectionValidationService;
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

class CompanionSeatRuleEnforcementTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;
    use ManagesFeatureFlags;

    private const COMPANION_SEAT = 'e3.0.0';

    private const STANDARD_SEAT = 'e3.0.1';

    private const WHEELCHAIR_SPACE = 'e6.0.0';

    private string $authToken;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $eventSeatMapId;

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
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, true);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));

        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct(priceType: 'FREE');
        $this->priceId = $this->insertPrice($this->productId, null);

        $this->eventSeatMapId = $this->insertEventSeatMap($this->layoutWithCompanionSeat(), ['b_standard' => [$this->productId]]);
    }

    public function test_a_public_order_for_a_companion_seat_alone_is_refused(): void
    {
        $this->createPublicOrder([self::COMPANION_SEAT, self::STANDARD_SEAT])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.products.0', 'Companion seats can only be booked together with a wheelchair space');

        $this->assertSame(0, DB::table('seat_claims')->where('event_occurrence_id', $this->occurrenceId)->count());
    }

    public function test_a_public_order_pairing_a_companion_seat_with_a_wheelchair_space_is_accepted(): void
    {
        $this->createPublicOrder([self::WHEELCHAIR_SPACE, self::COMPANION_SEAT])
            ->assertStatus(ResponseCodes::HTTP_CREATED);

        $this->assertEqualsCanonicalizing(
            [self::WHEELCHAIR_SPACE, self::COMPANION_SEAT],
            DB::table('seat_claims')->where('event_occurrence_id', $this->occurrenceId)->pluck('seat_uid')->all(),
        );
    }

    public function test_an_organizer_can_seat_a_manual_attendee_in_a_companion_seat_alone(): void
    {
        $this->createManualAttendee(self::COMPANION_SEAT)
            ->assertStatus(ResponseCodes::HTTP_CREATED)
            ->assertJsonPath('data.seat_uid', self::COMPANION_SEAT);
    }

    public function test_the_box_office_selection_is_exempt_from_the_companion_rule(): void
    {
        $selection = [[
            'product_id' => $this->productId,
            'event_occurrence_id' => $this->occurrenceId,
            'quantities' => [['quantity' => 1, 'seat_uids' => [self::COMPANION_SEAT]]],
        ]];

        app(SeatSelectionValidationService::class)->validate($this->eventId, $selection, enforceSelectionRules: false);

        $this->expectException(SeatSelectionInvalidException::class);
        app(SeatSelectionValidationService::class)->validate($this->eventId, $selection);
    }

    public function test_a_buyer_cannot_move_into_a_companion_seat_without_a_wheelchair_space(): void
    {
        $this->updateEventSeatMap($this->eventSeatMapId, ['allow_seat_change' => true]);
        $attendee = $this->createManualAttendee(self::STANDARD_SEAT)->json('data');
        $orderShortId = DB::table('orders')->where('id', $attendee['order_id'])->value('short_id');

        $this->putJson(
            "/public/events/{$this->eventId}/order/$orderShortId/attendees/{$attendee['short_id']}/seat",
            ['seat_uid' => self::COMPANION_SEAT],
        )
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.seat_uid.0', 'Companion seats can only be booked together with a wheelchair space');

        $this->assertDatabaseHas('attendees', ['id' => $attendee['id'], 'seat_uid' => self::STANDARD_SEAT]);
    }

    public function test_an_organizer_can_move_an_attendee_into_a_companion_seat(): void
    {
        $attendeeId = $this->createManualAttendee(self::STANDARD_SEAT)->json('data.id');

        $this->putJson(
            "/events/{$this->eventId}/attendees/$attendeeId/seat",
            ['seat_uid' => self::COMPANION_SEAT],
            $this->authHeaders(),
        )->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertDatabaseHas('attendees', ['id' => $attendeeId, 'seat_uid' => self::COMPANION_SEAT]);
    }

    private function layoutWithCompanionSeat(): array
    {
        $layout = $this->seatMapFixture('theatre');
        foreach ($layout['areas'][0]['elements'] as &$element) {
            if ($element['id'] === 'e3') {
                $element['overrides']['0.0'] = ['comp' => true];
                $element['seats'][0]['comp'] = true;
            }
        }

        return $layout;
    }

    /**
     * @param  string[]  $seatUids
     */
    private function createPublicOrder(array $seatUids): TestResponse
    {
        return $this->postJson("/public/events/{$this->eventId}/order", [
            'products' => [[
                'product_id' => $this->productId,
                'event_occurrence_id' => $this->occurrenceId,
                'quantities' => [[
                    'price_id' => $this->priceId,
                    'quantity' => count($seatUids),
                    'seat_uids' => $seatUids,
                ]],
            ]],
        ]);
    }

    private function createManualAttendee(string $seatUid): TestResponse
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
