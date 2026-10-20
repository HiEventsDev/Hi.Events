<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\CreatesSsoConnections;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\TestCase;

class SsoEnforcementTwoFactorTest extends TestCase
{
    use CreatesSsoConnections;
    use DatabaseTransactions;
    use ManagesFeatureFlags;

    private const PASSWORD = 'CorrectHorse123!';

    private const RECOVERY_CODE = 'abcd-efgh-ijkl';

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setFeatureFlagDefault(FeatureFlag::ENTERPRISE_SSO, true);

        Mail::fake();
        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);
        config()->set('app.saas_mode_enabled', false);
        $this->fakeConnectionIssuer();
        $this->secret = (new Google2FA)->generateSecretKey(32);
    }

    public function test_an_enforced_member_with_two_factor_is_told_to_use_sso_before_being_asked_for_a_code(): void
    {
        [, $accountId] = $this->enforcingAccount();
        $member = $this->enforcedMember($accountId, 'grace@acme.test');
        $recoveryCodes = $member->fresh()->two_factor_recovery_codes;

        $this->postJson('/auth/login', ['email' => 'grace@acme.test', 'password' => self::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED')
            ->assertJsonPath('context.email', 'grace@acme.test')
            ->assertJsonMissingPath('two_factor_challenge_token');

        $this->assertSame($recoveryCodes, $member->fresh()->two_factor_recovery_codes);
    }

    public function test_an_enforced_member_with_two_factor_still_gets_a_challenge_when_signing_in_through_the_connection(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        $this->enforcedMember($accountId, 'grace@acme.test');
        $flow = $this->startConnectionFlow($connection);

        $challenge = $this->exchangeOidcCode($flow, $this->connectionClaims('okta-grace', 'grace@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'TWO_FACTOR_REQUIRED')
            ->json('data.two_factor_challenge_token');

        $this->postJson('/auth/login/two-factor', [
            'challenge_token' => $challenge,
            'code' => (new Google2FA)->getCurrentOtp($this->secret),
            'sso_binding' => $flow['binding'],
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_an_enforced_member_signing_in_to_another_account_still_gets_a_challenge(): void
    {
        [, $accountId] = $this->enforcingAccount();
        $member = $this->enforcedMember($accountId, 'grace@acme.test');
        $otherAccountId = User::factory()->withAccount()->create()->accounts()->first()->id;
        $member->accounts()->attach($otherAccountId, [
            'role' => Role::ORGANIZER->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => false,
        ]);

        $this->postJson('/auth/login', ['email' => 'grace@acme.test', 'password' => self::PASSWORD, 'account_id' => $otherAccountId])
            ->assertOk()
            ->assertJson(['two_factor_required' => true]);

        $this->postJson('/auth/login', ['email' => 'grace@acme.test', 'password' => self::PASSWORD, 'account_id' => $accountId])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED');
    }

    /**
     * @return array{0: User, 1: int, 2: AccountSsoConnection}
     */
    private function enforcingAccount(): array
    {
        $owner = User::factory()->password(self::PASSWORD)->withTwoFactor($this->secret)->withAccount()->create();
        $accountId = $owner->accounts()->first()->id;
        $connection = $this->createConnection($accountId, ['enforce_sso' => true]);
        $this->addDomain($accountId, 'acme.test');

        return [$owner, $accountId, $connection];
    }

    private function enforcedMember(int $accountId, string $email): User
    {
        $user = User::factory()
            ->password(self::PASSWORD)
            ->withTwoFactor($this->secret, [self::RECOVERY_CODE])
            ->create(['email' => $email]);
        $user->accounts()->attach($accountId, [
            'role' => Role::ORGANIZER->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => false,
            'sso_enforced' => true,
        ]);

        return $user;
    }
}
