<?php

namespace Tests\Feature\Http\Actions\SeatMaps;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Concerns\ManagesLicence;
use Tests\TestCase;

class SeatMapCrudTest extends TestCase
{
    use DatabaseTransactions;
    use ManagesFeatureFlags;
    use ManagesLicence;

    private string $authToken;

    private int $accountId;

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

        $this->setFeatureFlagDefault(FeatureFlag::SEATING, false);

        [$this->authToken, $this->accountId] = $this->makeAuthenticatedUser();
        $this->organizerId = $this->makeOrganizer($this->accountId);
        $this->enableSeating($this->accountId);
    }

    public function test_full_crud_flow(): void
    {
        $create = $this->postJson($this->url(), [
            'name' => 'Main auditorium',
            'layout' => $this->fixture('theatre'),
        ], $this->authHeaders());

        $create->assertStatus(ResponseCodes::HTTP_CREATED);
        $this->assertSame(402, $create->json('data.seat_count'));
        $this->assertSame(1, $create->json('data.version'));
        $this->assertSame('Stalls', $create->json('data.layout.areas.0.name'));
        $seatMapId = $create->json('data.id');

        $list = $this->getJson($this->url().'?query=auditorium', $this->authHeaders());
        $list->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertCount(1, $list->json('data'));
        $this->assertArrayNotHasKey('layout', $list->json('data.0'));
        $this->assertArrayHasKey('allowed_sorts', $list->json('meta'));

        $update = $this->putJson($this->url($seatMapId), [
            'name' => 'Studio',
            'layout' => $this->fixture('thrust'),
        ], $this->authHeaders());
        $update->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame('Studio', $update->json('data.name'));
        $this->assertSame(193, $update->json('data.seat_count'));
        $this->assertSame(2, $update->json('data.version'));

        $this->getJson($this->url($seatMapId), $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.layout.areas.0.name', 'Studio');

        $this->deleteJson($this->url($seatMapId), [], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_NO_CONTENT);

        $this->getJson($this->url($seatMapId), $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
    }

    public function test_a_fetched_layout_with_seat_overrides_saves_back_unchanged(): void
    {
        $seatMapId = $this->postJson($this->url(), [
            'name' => 'Main auditorium',
            'layout' => $this->fixture('theatre'),
        ], $this->authHeaders())->json('data.id');

        $fetched = $this->getJson($this->url($seatMapId), $this->authHeaders());
        $this->assertTrue($fetched->json('data.layout.areas.0.elements.5.overrides')['0.0']['acc']);

        $this->putJson($this->url($seatMapId), [
            'name' => 'Main auditorium',
            'layout' => $fetched->json('data.layout'),
        ], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_invalid_layout_returns_validation_errors_with_paths(): void
    {
        $layout = $this->fixture('conference');
        $layout['areas'][0]['elements'][1]['seats'][0]['band'] = 'b_missing';

        $this->postJson($this->url(), ['name' => 'Broken', 'layout' => $layout], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['layout.areas.0.elements.1.seats.0.band']);

        $this->assertSame(0, DB::table('seat_maps')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_account_without_seating_enabled_can_list_but_not_create(): void
    {
        $this->useCloudLicence();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, false);

        $this->getJson($this->url(), $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);

        $this->postJson($this->url(), ['name' => 'Nope', 'layout' => $this->fixture('empty')], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
    }

    public function test_flag_default_enables_seating_for_accounts_without_an_override(): void
    {
        $this->useCloudLicence();
        [$otherToken, $otherAccountId] = $this->makeAuthenticatedUser();
        $otherOrganizerId = $this->makeOrganizer($otherAccountId);
        $this->setFeatureFlagDefault(FeatureFlag::SEATING, true);

        $this->getJson("/organizers/$otherOrganizerId/seat-maps", $this->authHeaders($otherToken))
            ->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_override_disables_seating_even_when_enabled_by_default(): void
    {
        $this->useCloudLicence();
        $this->setFeatureFlagDefault(FeatureFlag::SEATING, true);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, false);

        $this->postJson($this->url(), ['name' => 'Nope', 'layout' => $this->fixture('empty')], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
    }

    public function test_seat_maps_are_isolated_between_accounts(): void
    {
        $seatMapId = $this->postJson($this->url(), [
            'name' => 'Private',
            'layout' => $this->fixture('empty'),
        ], $this->authHeaders())->json('data.id');

        [$otherToken, $otherAccountId] = $this->makeAuthenticatedUser();
        $otherOrganizerId = $this->makeOrganizer($otherAccountId);
        $this->enableSeating($otherAccountId);

        $this->getJson("/organizers/{$this->organizerId}/seat-maps/$seatMapId", $this->authHeaders($otherToken))
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);

        $this->getJson("/organizers/$otherOrganizerId/seat-maps/$seatMapId", $this->authHeaders($otherToken))
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->deleteJson("/organizers/$otherOrganizerId/seat-maps/$seatMapId", [], $this->authHeaders($otherToken))
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
    }

    private function url(?int $seatMapId = null): string
    {
        return "/organizers/{$this->organizerId}/seat-maps".($seatMapId === null ? '' : "/$seatMapId");
    }

    private function fixture(string $template): array
    {
        return json_decode(
            file_get_contents(base_path("tests/Fixtures/seating/$template.json")),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function enableSeating(int $accountId): void
    {
        $this->setFeatureFlagOverride($accountId, FeatureFlag::SEATING, true);
    }

    private function makeAuthenticatedUser(): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        return [JWTAuth::claims(['account_id' => $accountId])->fromUser($user), $accountId];
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

    private function authHeaders(?string $token = null): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => 'Bearer '.($token ?? $this->authToken)];
    }
}
