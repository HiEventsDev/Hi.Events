<?php

namespace Tests\Feature\Http\Actions\Locations;

use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LocationCrudTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

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

        [$this->user, $this->authToken, $this->accountId] = $this->makeAuthenticatedUser();
        $this->organizerId = $this->makeOrganizer($this->accountId);
    }

    public function test_full_crud_flow_with_country_normalization(): void
    {
        $create = $this->postJson("/organizers/{$this->organizerId}/locations", [
            'name' => 'The Venue',
            'structured_address' => ['city' => 'Dublin', 'country' => 'ie'],
        ], $this->authHeaders());

        $create->assertStatus(ResponseCodes::HTTP_CREATED);
        $this->assertSame('IE', $create->json('data.structured_address.country'));
        $locationId = $create->json('data.id');

        $list = $this->getJson("/organizers/{$this->organizerId}/locations?query=venue", $this->authHeaders());
        $list->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertCount(1, $list->json('data'));
        $this->assertArrayHasKey('allowed_sorts', $list->json('meta'));

        $update = $this->putJson("/organizers/{$this->organizerId}/locations/{$locationId}", [
            'name' => 'Renamed Venue',
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
        ], $this->authHeaders());
        $update->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame('Renamed Venue', $update->json('data.name'));

        $this->deleteJson("/organizers/{$this->organizerId}/locations/{$locationId}", [], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_NO_CONTENT);

        $this->assertSame(0, count($this->getJson(
            "/organizers/{$this->organizerId}/locations",
            $this->authHeaders(),
        )->json('data')));
    }

    public function test_create_preserves_ampersands_in_plain_text_fields(): void
    {
        $create = $this->postJson("/organizers/{$this->organizerId}/locations", [
            'name' => 'Barnes & Noble',
            'structured_address' => ['venue_name' => 'Tom & Jerry', 'city' => 'Dublin', 'country' => 'IE'],
        ], $this->authHeaders());

        $create->assertStatus(ResponseCodes::HTTP_CREATED);
        $this->assertSame('Barnes & Noble', $create->json('data.name'));
        $this->assertSame('Tom & Jerry', $create->json('data.structured_address.venue_name'));

        $list = $this->getJson("/organizers/{$this->organizerId}/locations", $this->authHeaders());
        $this->assertSame('Barnes & Noble', $list->json('data.0.name'));
        $this->assertSame('Tom & Jerry', $list->json('data.0.structured_address.venue_name'));
    }

    public function test_create_stores_angle_brackets_and_markup_verbatim(): void
    {
        $create = $this->postJson("/organizers/{$this->organizerId}/locations", [
            'name' => '<script>alert(1)</script>Cafe',
            'structured_address' => ['venue_name' => 'I <3 NY', 'city' => 'Dublin', 'country' => 'IE'],
        ], $this->authHeaders());

        $create->assertStatus(ResponseCodes::HTTP_CREATED);
        $this->assertSame('<script>alert(1)</script>Cafe', $create->json('data.name'));
        $this->assertSame('I <3 NY', $create->json('data.structured_address.venue_name'));

        $list = $this->getJson("/organizers/{$this->organizerId}/locations", $this->authHeaders());
        $this->assertSame('<script>alert(1)</script>Cafe', $list->json('data.0.name'));
        $this->assertSame('I <3 NY', $list->json('data.0.structured_address.venue_name'));
    }

    public function test_create_accepts_numeric_string_coordinates(): void
    {
        $create = $this->postJson("/organizers/{$this->organizerId}/locations", [
            'name' => 'Quoted Coords',
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
            'latitude' => '45.5',
            'longitude' => '-122.1',
        ], $this->authHeaders());

        $create->assertStatus(ResponseCodes::HTTP_CREATED);
        $this->assertEqualsWithDelta(45.5, (float) $create->json('data.latitude'), 0.000001);
        $this->assertEqualsWithDelta(-122.1, (float) $create->json('data.longitude'), 0.000001);
    }

    public function test_form_encoded_create_accepts_coordinates(): void
    {
        $create = $this->post("/organizers/{$this->organizerId}/locations", [
            'name' => 'Form Encoded',
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
            'latitude' => '45.5',
            'longitude' => '-122.1',
        ], array_merge($this->authHeaders(), ['Accept' => 'application/json']));

        $create->assertStatus(ResponseCodes::HTTP_CREATED);
        $this->assertEqualsWithDelta(45.5, (float) $create->json('data.latitude'), 0.000001);
    }

    public function test_update_accepts_numeric_string_coordinates(): void
    {
        $locationId = $this->postJson("/organizers/{$this->organizerId}/locations", [
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
        ], $this->authHeaders())->json('data.id');

        $update = $this->putJson("/organizers/{$this->organizerId}/locations/{$locationId}", [
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
            'latitude' => '53.35',
            'longitude' => '-6.26',
        ], $this->authHeaders());

        $update->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertEqualsWithDelta(53.35, (float) $update->json('data.latitude'), 0.000001);
    }

    public function test_autocomplete_with_array_query_parameter_returns_empty_results(): void
    {
        $response = $this->getJson(
            "/organizers/{$this->organizerId}/locations/autocomplete?query[]=x",
            $this->authHeaders(),
        );

        $response->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame([], $response->json('data'));
    }

    public function test_create_without_any_address_field_fails_validation(): void
    {
        $response = $this->postJson("/organizers/{$this->organizerId}/locations", [
            'structured_address' => [],
        ], $this->authHeaders());

        $response->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('structured_address', $response->json('errors'));
    }

    public function test_create_reuses_existing_location_for_same_provider_place(): void
    {
        $payload = [
            'name' => 'Reusable Venue',
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
            'provider' => 'google',
            'provider_place_id' => 'ChIJ-reuse',
        ];

        $first = $this->postJson("/organizers/{$this->organizerId}/locations", $payload, $this->authHeaders());
        $second = $this->postJson("/organizers/{$this->organizerId}/locations", $payload, $this->authHeaders());

        $first->assertStatus(ResponseCodes::HTTP_CREATED);
        $second->assertStatus(ResponseCodes::HTTP_CREATED);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
    }

    public function test_update_to_provider_place_used_by_another_location_returns_conflict(): void
    {
        $this->postJson("/organizers/{$this->organizerId}/locations", [
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
            'provider' => 'google',
            'provider_place_id' => 'ChIJ-taken',
        ], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_CREATED);

        $other = $this->postJson("/organizers/{$this->organizerId}/locations", [
            'structured_address' => ['city' => 'Cork', 'country' => 'IE'],
        ], $this->authHeaders());

        $response = $this->putJson("/organizers/{$this->organizerId}/locations/{$other->json('data.id')}", [
            'structured_address' => ['city' => 'Cork', 'country' => 'IE'],
            'provider' => 'google',
            'provider_place_id' => 'ChIJ-taken',
        ], $this->authHeaders());

        $response->assertStatus(ResponseCodes::HTTP_CONFLICT);
    }

    public function test_delete_referenced_location_returns_conflict(): void
    {
        $locationId = $this->postJson("/organizers/{$this->organizerId}/locations", [
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
        ], $this->authHeaders())->json('data.id');

        $eventId = DB::table('events')->insertGetId([
            'title' => 'Referencing Event',
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_locations')->insert([
            'short_id' => 'el_'.uniqid(),
            'event_id' => $eventId,
            'type' => 'IN_PERSON',
            'location_id' => $locationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/organizers/{$this->organizerId}/locations/{$locationId}", [], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CONFLICT);
    }

    public function test_cross_account_access_is_denied(): void
    {
        [, $otherToken] = $this->makeAuthenticatedUser();

        $this->getJson("/organizers/{$this->organizerId}/locations", $this->authHeaders($otherToken))
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);

        $this->postJson("/organizers/{$this->organizerId}/locations", [
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
        ], $this->authHeaders($otherToken))->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
    }

    public function test_cross_organizer_location_is_not_found(): void
    {
        $locationId = $this->postJson("/organizers/{$this->organizerId}/locations", [
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
        ], $this->authHeaders())->json('data.id');

        $otherOrganizerId = $this->makeOrganizer($this->accountId);

        $this->putJson("/organizers/{$otherOrganizerId}/locations/{$locationId}", [
            'structured_address' => ['city' => 'Dublin', 'country' => 'IE'],
        ], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->deleteJson("/organizers/{$otherOrganizerId}/locations/{$locationId}", [], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->flushGuards();

        $this->getJson("/organizers/{$this->organizerId}/locations")
            ->assertStatus(ResponseCodes::HTTP_UNAUTHORIZED);
    }

    private function makeAuthenticatedUser(): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $token = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);

        return [$user, $token, $accountId];
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
        $this->flushGuards();

        return ['Authorization' => 'Bearer '.($token ?? $this->authToken)];
    }

    private function flushGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
