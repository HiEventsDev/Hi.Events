<?php

namespace Tests\Feature\Http\Actions\Organizers;

use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class OrganizerFirstDayOfWeekTest extends TestCase
{
    use DatabaseTransactions;

    private string $authToken;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        [$this->authToken, $accountId] = $this->makeAuthenticatedUser();
        $this->organizerId = $this->makeOrganizer($accountId);
    }

    public function test_defaults_to_monday(): void
    {
        $this->getJson("/organizers/{$this->organizerId}", $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.first_day_of_week', 1);
    }

    public function test_edit_saves_first_day_of_week(): void
    {
        $this->postJson("/organizers/{$this->organizerId}", $this->organizerPayload(['first_day_of_week' => 0]), $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.first_day_of_week', 0);

        $this->getJson("/organizers/{$this->organizerId}", $this->authHeaders())
            ->assertJsonPath('data.first_day_of_week', 0);
    }

    public function test_edit_without_first_day_of_week_keeps_existing_value(): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update(['first_day_of_week' => 6]);

        $this->postJson("/organizers/{$this->organizerId}", $this->organizerPayload(), $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.first_day_of_week', 6);
    }

    public function test_edit_rejects_out_of_range_day(): void
    {
        $this->postJson("/organizers/{$this->organizerId}", $this->organizerPayload(['first_day_of_week' => 7]), $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('first_day_of_week');
    }

    public function test_public_organizer_exposes_first_day_of_week(): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update([
            'first_day_of_week' => 0,
            'status' => OrganizerStatus::LIVE->name,
        ]);

        $this->getJson("/public/organizers/{$this->organizerId}")
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.first_day_of_week', 0);
    }

    private function organizerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Organizer',
            'email' => 'organizer@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ], $overrides);
    }

    private function makeAuthenticatedUser(): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $token = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);

        return [$token, $accountId];
    }

    private function makeOrganizer(int $accountId): int
    {
        return DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Test Organizer',
            'email' => 'organizer-'.uniqid().'@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function authHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
