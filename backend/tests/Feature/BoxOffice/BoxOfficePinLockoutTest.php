<?php

namespace Tests\Feature\BoxOffice;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinService;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response as ResponseCodes;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class BoxOfficePinLockoutTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const PIN = '482915';

    private string $boxOfficeShortId;

    private int $boxOfficeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->insertOccurrence();

        $this->boxOfficeShortId = 'bo_'.uniqid();
        $this->boxOfficeId = DB::table('box_offices')->insertGetId([
            'event_id' => $this->eventId,
            'short_id' => $this->boxOfficeShortId,
            'name' => 'Front door',
            'pin_hash' => app(BoxOfficePinService::class)->hash(self::PIN),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_device_is_locked_out_after_five_wrong_pins_even_with_the_right_one(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->login('000000', '10.1.0.1')->assertStatus(ResponseCodes::HTTP_UNAUTHORIZED);
        }

        $this->login(self::PIN, '10.1.0.1')->assertStatus(ResponseCodes::HTTP_TOO_MANY_REQUESTS);
        $this->login(self::PIN, '10.1.0.2')->assertCreated();
    }

    public function test_a_staff_login_does_not_wipe_failures_other_devices_built_up(): void
    {
        for ($device = 1; $device <= 3; $device++) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->login('000000', '10.2.0.'.$device)->assertStatus(ResponseCodes::HTTP_UNAUTHORIZED);
            }

            $this->login(self::PIN, '10.2.1.'.$device)->assertCreated();
        }

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->login('000000', '10.2.0.4')->assertStatus(ResponseCodes::HTTP_UNAUTHORIZED);
        }

        $this->login('000000', '10.2.0.99')->assertStatus(ResponseCodes::HTTP_TOO_MANY_REQUESTS);
        $this->login(self::PIN, '10.2.0.100')->assertStatus(ResponseCodes::HTTP_TOO_MANY_REQUESTS);
    }

    public function test_one_locked_out_device_cannot_lock_the_whole_box_office(): void
    {
        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->login('000000', '10.3.0.1');
        }

        $this->login(self::PIN, '10.3.0.2')->assertCreated();
    }

    public function test_resetting_the_pin_lifts_the_lockout_of_a_venue_that_mistyped_it(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->login('000000', '10.4.0.1')->assertStatus(ResponseCodes::HTTP_UNAUTHORIZED);
        }
        $this->login(self::PIN, '10.4.0.1')->assertStatus(ResponseCodes::HTTP_TOO_MANY_REQUESTS);

        $newPin = $this->postJson(
            "/events/{$this->eventId}/box-offices/{$this->boxOfficeId}/reset-pin",
            [],
            ['Authorization' => 'Bearer '.JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId))],
        )->assertOk()->json('data.pin');

        $this->login($newPin, '10.4.0.1')->assertCreated();
    }

    private function login(string $pin, string $ip): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/public/box-offices/'.$this->boxOfficeShortId.'/sessions', [
                'operator_name' => 'Sam',
                'pin' => $pin,
            ]);
    }
}
