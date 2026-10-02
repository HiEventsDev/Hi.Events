<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Licensing;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\Licensing\LicenceService;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Concerns\ManagesLicence;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class LicenceGatingTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;
    use ManagesFeatureFlags;
    use ManagesLicence;

    private const RULES = ['prevent_orphan_seats' => true, 'max_seats_per_order' => null, 'allow_seat_change' => false];

    private const LICENCE_REQUIRED = 'This feature requires an active Hi.Events Enterprise licence';

    private string $authToken;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['app.saas_mode_enabled' => false]);
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
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), ['b_standard' => [$this->productId]]);
    }

    public function test_an_active_licence_allows_configuration(): void
    {
        $this->useLicence(expiresAt: '2099-01-01');

        $this->postJson("/events/{$this->eventId}/box-offices", ['name' => 'Front door'], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CREATED);
        $this->putJson("/events/{$this->eventId}/seat-map/rules", self::RULES, $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_a_lapsed_licence_blocks_configuration_but_not_reads(): void
    {
        $this->useLicence(expiresAt: '2020-01-01');

        $this->postJson("/events/{$this->eventId}/box-offices", ['name' => 'Front door'], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN)
            ->assertJsonPath('message', self::LICENCE_REQUIRED)
            ->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');
        $this->putJson("/events/{$this->eventId}/seat-map/rules", self::RULES, $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
        $this->postJson('/public/box-offices/bo_anything/sessions', ['operator_name' => 'Ada', 'pin' => '1234'])
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN)
            ->assertJsonPath('message', self::LICENCE_REQUIRED);

        $this->getJson("/events/{$this->eventId}/box-offices", $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);
        $this->getJson("/events/{$this->eventId}/seat-map", $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_a_lapsed_licence_can_still_list_and_delete_seat_map_templates(): void
    {
        $seatMapId = DB::table('seat_maps')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => 'Main hall',
            'layout' => json_encode($this->seatMapFixture('theatre')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->useLicence(expiresAt: '2020-01-01');

        $this->getJson("/organizers/{$this->organizerId}/seat-maps", $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.0.id', $seatMapId);
        $this->getJson("/organizers/{$this->organizerId}/seat-maps/$seatMapId", $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);
        $this->postJson("/organizers/{$this->organizerId}/seat-maps", ['name' => 'New', 'layout' => $this->seatMapFixture('theatre')], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);

        $this->deleteJson("/organizers/{$this->organizerId}/seat-maps/$seatMapId", [], $this->authHeaders())
            ->assertSuccessful();
        $this->assertNotNull(DB::table('seat_maps')->where('id', $seatMapId)->value('deleted_at'));
    }

    public function test_the_features_in_use_reported_to_a_user_are_those_of_their_own_account(): void
    {
        $this->useLicence(expiresAt: null);
        [$otherToken] = $this->makeOtherAccountToken();

        $this->getJson('/users/me', $this->authHeaders())
            ->assertJsonPath('data.licence.features_in_use', ['seating']);
        $this->getJson('/users/me', $this->authHeaders($otherToken))
            ->assertJsonPath('data.licence.features_in_use', []);
    }

    public function test_the_licence_state_can_be_simulated_per_request_outside_production(): void
    {
        $this->useLicence(expiresAt: null, env: 'e2e');

        $this->getJson('/users/me', [...$this->authHeaders(), ...$this->simulate('LAPSED')])
            ->assertJsonPath('data.licence.status', 'LAPSED');
        $this->getJson('/public/compliance', $this->simulate('ACTIVE:white_label'))
            ->assertJsonPath('data.licence_status', 'ACTIVE')
            ->assertJsonPath('data.white_label', true);
        $this->putJson("/events/{$this->eventId}/seat-map/rules", self::RULES, [...$this->authHeaders(), ...$this->simulate('NONE')])
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
        $this->app->forgetScopedInstances();
        $this->getJson('/users/me', $this->authHeaders())
            ->assertJsonPath('data.licence.status', 'DEV');
    }

    public function test_the_licence_simulation_header_is_ignored_on_a_local_install(): void
    {
        $this->useLicence(expiresAt: null, env: 'local');

        $this->getJson('/public/compliance', $this->simulate('ACTIVE:white_label'))
            ->assertJsonPath('data.licence_status', 'NONE')
            ->assertJsonPath('data.white_label', false);
    }

    public function test_the_licence_simulation_header_is_ignored_in_production(): void
    {
        $this->useLicence(expiresAt: null);

        $this->getJson('/public/compliance', $this->simulate('ACTIVE:white_label'))
            ->assertJsonPath('data.licence_status', 'NONE')
            ->assertJsonPath('data.white_label', false);
    }

    public function test_a_lapsed_licence_still_sells_seated_tickets(): void
    {
        $this->useLicence(expiresAt: '2020-01-01');

        $this->postJson("/public/events/{$this->eventId}/order", [
            'products' => [[
                'product_id' => $this->productId,
                'event_occurrence_id' => $this->occurrenceId,
                'quantities' => [['price_id' => $this->priceId, 'quantity' => 1, 'seat_uids' => ['e3.0.1']]],
            ]],
        ])->assertStatus(ResponseCodes::HTTP_CREATED);

        $this->assertSame(
            ['e3.0.1'],
            DB::table('seat_claims')->where('event_occurrence_id', $this->occurrenceId)->pluck('seat_uid')->all(),
        );
    }

    public function test_no_licence_turns_the_features_off_even_on_a_local_install(): void
    {
        foreach (['production', 'local'] as $env) {
            $this->useLicence(expiresAt: null, env: $env);

            $this->getJson('/users/me', $this->authHeaders())
                ->assertStatus(ResponseCodes::HTTP_OK)
                ->assertJsonPath('data.feature_flags.seating', false)
                ->assertJsonPath('data.feature_flags.box_office', false)
                ->assertJsonPath('data.licence.status', 'NONE')
                ->assertJsonPath('data.licence.invalid_reason', null)
                ->assertJsonPath('data.licence.features_in_use', ['seating']);
            $this->postJson("/events/{$this->eventId}/box-offices", ['name' => 'Front door'], $this->authHeaders())
                ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
            $this->getJson('/public/instance')
                ->assertJsonPath('data.white_label', false)
                ->assertJsonPath('data.dev_mode', false);
        }
    }

    public function test_the_development_key_unlocks_features_without_white_label(): void
    {
        $this->useLicenceKey('development');

        $this->getJson('/users/me', $this->authHeaders())
            ->assertJsonPath('data.feature_flags.seating', true)
            ->assertJsonPath('data.licence.status', 'DEV')
            ->assertJsonPath('data.licence.invalid_reason', null);
        $this->getJson('/public/instance')
            ->assertJsonPath('data.white_label', false)
            ->assertJsonPath('data.dev_mode', true);
    }

    public function test_only_hi_events_cloud_rolls_licensed_features_out_by_feature_flag(): void
    {
        config(['app.saas_mode_enabled' => true]);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, false);

        $seatMap = ['name' => 'Main hall', 'layout' => $this->seatMapFixture('theatre')];

        $this->useLicence(expiresAt: '2099-12-31');
        $this->getJson('/users/me', $this->authHeaders())
            ->assertJsonPath('data.feature_flags.seating', true);
        $this->postJson("/organizers/{$this->organizerId}/seat-maps", $seatMap, $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CREATED);

        $this->useCloudLicence();
        $this->getJson('/users/me', $this->authHeaders())
            ->assertJsonPath('data.feature_flags.seating', false);
        $this->postJson("/organizers/{$this->organizerId}/seat-maps", $seatMap, $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
    }

    public function test_development_mode_is_not_flagged_when_no_licensed_feature_is_set_up(): void
    {
        foreach (['event_seat_maps', 'seat_maps', 'stripe_terminal_readers', 'box_offices'] as $table) {
            DB::table($table)->delete();
        }
        $this->useLicenceKey('development');

        $this->getJson('/users/me', $this->authHeaders())
            ->assertJsonPath('data.licence.status', 'DEV')
            ->assertJsonPath('data.licence.features_in_use', []);
        $this->getJson('/public/instance')
            ->assertJsonPath('data.dev_mode', false);
    }

    public function test_the_white_label_add_on_is_reported_publicly(): void
    {
        $this->useLicence(expiresAt: '2099-01-01', addOns: ['white_label']);

        $this->getJson('/public/instance')
            ->assertJsonPath('data.white_label', true)
            ->assertJsonPath('data.dev_mode', false);
    }

    public function test_compliance_reports_the_support_email_and_licence(): void
    {
        $this->useLicence(expiresAt: '2099-01-01', addOns: ['white_label']);
        config(['app.platform_support_email' => 'help@example.test']);

        $this->getJson('/public/compliance')
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.support_email', 'help@example.test')
            ->assertJsonPath('data.licence_status', 'ACTIVE')
            ->assertJsonPath('data.white_label', true);
    }

    public function test_compliance_reports_no_white_label_without_a_licence(): void
    {
        $this->useLicence(expiresAt: null);

        $this->getJson('/public/compliance')
            ->assertJsonPath('data.licence_status', 'NONE')
            ->assertJsonPath('data.white_label', false);
    }

    public function test_a_garbage_key_is_treated_as_no_licence(): void
    {
        config(['app.env' => 'production', 'licence.key' => 'hiev1.not.valid']);
        $this->app->forgetScopedInstances();

        $this->getJson('/users/me', $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.licence.status', 'NONE')
            ->assertJsonPath('data.licence.invalid_reason', 'The licence key could not be read');
    }

    public function test_a_lapsed_white_label_licence_counts_white_label_as_in_use(): void
    {
        foreach (['event_seat_maps', 'seat_maps', 'stripe_terminal_readers', 'box_offices'] as $table) {
            DB::table($table)->delete();
        }
        $this->useLicence(expiresAt: '2020-01-01', addOns: ['white_label']);

        $this->getJson('/users/me', $this->authHeaders())
            ->assertJsonPath('data.licence.status', 'LAPSED')
            ->assertJsonPath('data.licence.features_in_use', ['white_label']);
    }

    public function test_the_admin_licence_page_requires_a_superadmin(): void
    {
        $this->getJson('/admin/licence', $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
    }

    /**
     * @return array<string, string>
     */
    private function simulate(string $state): array
    {
        $this->app->forgetScopedInstances();

        return [LicenceService::SIMULATION_HEADER => $state];
    }

    /**
     * @return array{0: string}
     */
    private function makeOtherAccountToken(): array
    {
        $accountId = $this->accountId;
        $userId = $this->userId;
        $organizerId = $this->organizerId;
        $this->insertAccountAndOrganizer();
        $token = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));
        [$this->accountId, $this->userId, $this->organizerId] = [$accountId, $userId, $organizerId];

        return [$token];
    }

    private function authHeaders(?string $token = null): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => 'Bearer '.($token ?? $this->authToken)];
    }
}
