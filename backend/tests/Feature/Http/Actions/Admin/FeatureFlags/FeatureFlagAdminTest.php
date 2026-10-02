<?php

namespace Tests\Feature\Http\Actions\Admin\FeatureFlags;

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

class FeatureFlagAdminTest extends TestCase
{
    use DatabaseTransactions;
    use ManagesFeatureFlags;
    use ManagesLicence;

    private string $superAdminToken;

    private string $adminToken;

    private int $targetAccountId;

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
        $this->useCloudLicence();

        [$this->superAdminToken] = $this->makeUser('SUPERADMIN');
        [$this->adminToken, $this->targetAccountId] = $this->makeUser('ADMIN');
    }

    public function test_licensed_flags_are_hidden_from_the_admin_off_hi_events_cloud(): void
    {
        config(['app.saas_mode_enabled' => true]);
        $this->useLicence(expiresAt: '2099-12-31', env: 'testing');

        $this->getJson('/admin/feature-flags', $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(0, 'data');
        $this->getJson("/admin/accounts/{$this->targetAccountId}/feature-flags", $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(0, 'data');
    }

    public function test_setting_and_clearing_an_account_override(): void
    {
        $this->putJson($this->overrideUrl(), ['enabled' => true], $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.0.key', 'seating')
            ->assertJsonPath('data.0.override', true)
            ->assertJsonPath('data.0.enabled', true);

        $this->getJson('/users/me', $this->headers($this->adminToken))
            ->assertJsonPath('data.feature_flags.seating', true);

        $this->getJson('/admin/feature-flags/seating/overrides', $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonFragment(['account_id' => $this->targetAccountId, 'enabled' => true]);

        $this->putJson($this->overrideUrl(), ['enabled' => null], $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.0.override', null)
            ->assertJsonPath('data.0.enabled', false);

        $this->assertDatabaseMissing('account_feature_flag_overrides', ['account_id' => $this->targetAccountId]);
    }

    public function test_updating_the_default_applies_to_accounts_without_overrides(): void
    {
        $this->putJson('/admin/feature-flags/seating', ['enabled_by_default' => true], $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.enabled_by_default', true);

        $this->getJson('/users/me', $this->headers($this->adminToken))
            ->assertJsonPath('data.feature_flags.seating', true);

        $this->getJson('/admin/feature-flags', $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonFragment(['key' => 'seating', 'enabled_by_default' => true]);
    }

    public function test_setting_an_override_twice_updates_the_same_row(): void
    {
        $this->putJson($this->overrideUrl(), ['enabled' => true], $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK);
        $this->putJson($this->overrideUrl(), ['enabled' => false], $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.0.override', false);

        $this->assertSame(1, DB::table('account_feature_flag_overrides')->where('account_id', $this->targetAccountId)->count());
    }

    public function test_unknown_account_is_not_found(): void
    {
        $missingAccountId = (int) DB::table('accounts')->max('id') + 1000;

        $this->getJson("/admin/accounts/$missingAccountId/feature-flags", $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);

        $this->putJson("/admin/accounts/$missingAccountId/feature-flags/seating", ['enabled' => true], $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
    }

    public function test_unknown_flag_key_is_not_found(): void
    {
        $this->putJson("/admin/accounts/{$this->targetAccountId}/feature-flags/unknown", ['enabled' => true], $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
    }

    public function test_enabled_must_be_present(): void
    {
        $this->putJson($this->overrideUrl(), [], $this->headers($this->superAdminToken))
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['enabled']);
    }

    public function test_non_superadmin_cannot_manage_flags(): void
    {
        $this->getJson('/admin/feature-flags', $this->headers($this->adminToken))
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);

        $this->putJson($this->overrideUrl(), ['enabled' => true], $this->headers($this->adminToken))
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);

        $this->assertSame(0, DB::table('account_feature_flag_overrides')->where('account_id', $this->targetAccountId)->count());
    }

    private function overrideUrl(): string
    {
        return "/admin/accounts/{$this->targetAccountId}/feature-flags/seating";
    }

    private function makeUser(string $role): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        DB::table('account_users')->where('user_id', $user->id)->update(['role' => $role]);

        return [JWTAuth::claims(['account_id' => $accountId])->fromUser($user), $accountId];
    }

    private function headers(string $token): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => "Bearer $token", 'Accept' => 'application/json'];
    }
}
