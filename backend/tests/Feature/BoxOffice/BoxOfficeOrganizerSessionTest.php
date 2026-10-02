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

class BoxOfficeOrganizerSessionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private string $boxOfficeShortId;

    private int $boxOfficeId;

    private string $organizerToken;

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
            'pin_hash' => app(BoxOfficePinService::class)->hash('482915'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->organizerToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));
    }

    public function test_an_organizer_session_works_while_the_organizer_is_signed_in(): void
    {
        $session = $this->startOrganizerSession();

        $this->products($session, $this->organizerToken)->assertOk();
    }

    public function test_an_organizer_session_ends_once_the_device_is_no_longer_signed_in_as_the_organizer(): void
    {
        $session = $this->startOrganizerSession();

        $this->products($session, null)
            ->assertStatus(ResponseCodes::HTTP_UNAUTHORIZED)
            ->assertJsonPath('error_code', 'BOX_OFFICE_SESSION_EXPIRED');
    }

    public function test_resetting_the_pin_signs_out_organizer_sessions_too(): void
    {
        $session = $this->startOrganizerSession();

        $this->withAuth($this->organizerToken)
            ->postJson("/events/{$this->eventId}/box-offices/{$this->boxOfficeId}/reset-pin")
            ->assertOk();

        $this->products($session, $this->organizerToken)->assertStatus(ResponseCodes::HTTP_UNAUTHORIZED);
    }

    private function startOrganizerSession(): string
    {
        return $this->withAuth($this->organizerToken)
            ->postJson("/public/box-offices/{$this->boxOfficeShortId}/sessions", ['operator_name' => 'Organizer'])
            ->assertCreated()
            ->json('data.token');
    }

    private function products(string $session, ?string $authToken): TestResponse
    {
        return $this->withAuth($authToken)
            ->withHeader('X-Box-Office-Session', $session)
            ->getJson("/public/box-offices/{$this->boxOfficeShortId}/products");
    }

    private function withAuth(?string $authToken): static
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
        $this->flushHeaders();

        return $authToken === null ? $this : $this->withHeader('Authorization', 'Bearer '.$authToken);
    }
}
