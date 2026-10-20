<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Models\User;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CreatesSsoConnections;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\TestCase;

class SsoInvitationEnforcementTest extends TestCase
{
    use ActsAsAccountUsers;
    use CreatesSsoConnections;
    use DatabaseTransactions;
    use ManagesFeatureFlags;

    private const PASSWORD = 'CorrectHorse123!';

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
    }

    public function test_the_invitation_says_when_the_account_requires_single_sign_on(): void
    {
        [$owner, $accountId] = $this->enforcingAccount();
        $enforced = $this->invite($owner, $accountId, 'new.hire@acme.test');
        $contractor = $this->invite($owner, $accountId, 'contractor@agency.test');

        $this->getJson('/auth/invitation/'.$this->invitationToken($enforced, $accountId))
            ->assertOk()
            ->assertJsonPath('data.sso_required', true);
        $this->getJson('/auth/invitation/'.$this->invitationToken($contractor, $accountId))
            ->assertOk()
            ->assertJsonPath('data.sso_required', false);
    }

    public function test_an_invitee_who_must_use_single_sign_on_cannot_accept_with_a_password(): void
    {
        [$owner, $accountId] = $this->enforcingAccount();
        $invitee = $this->invite($owner, $accountId, 'new.hire@acme.test');

        $this->postJson('/auth/invitation/'.$this->invitationToken($invitee, $accountId), [
            'first_name' => 'New',
            'last_name' => 'Hire',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'timezone' => 'Europe/Dublin',
        ])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED')
            ->assertJsonPath('context.email', 'new.hire@acme.test');

        $this->assertNull($invitee->fresh()->password);
        $this->assertSame(UserStatus::INVITED->name, $this->membershipStatus($invitee, $accountId));
    }

    public function test_an_invitee_outside_the_verified_domains_still_accepts_with_a_password(): void
    {
        [$owner, $accountId] = $this->enforcingAccount();
        $invitee = $this->invite($owner, $accountId, 'contractor@agency.test');

        $this->postJson('/auth/invitation/'.$this->invitationToken($invitee, $accountId), [
            'first_name' => 'Con',
            'last_name' => 'Tractor',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'timezone' => 'Europe/Dublin',
        ])->assertNoContent();

        $this->assertSame(UserStatus::ACTIVE->name, $this->membershipStatus($invitee, $accountId));
    }

    public function test_an_invitee_who_must_use_single_sign_on_joins_by_signing_in_through_the_connection(): void
    {
        [$owner, $accountId, $connection] = $this->enforcingAccount();
        $invitee = $this->invite($owner, $accountId, 'new.hire@acme.test');

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-new-hire', 'new.hire@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');

        $this->assertSame(UserStatus::ACTIVE->name, $this->membershipStatus($invitee, $accountId));
    }

    /**
     * @return array{0: User, 1: int, 2: AccountSsoConnection}
     */
    private function enforcingAccount(): array
    {
        $owner = User::factory()->password(self::PASSWORD)->withTwoFactor((new Google2FA)->generateSecretKey(32))->withAccount()->create();
        $accountId = $owner->accounts()->first()->id;
        $connection = $this->createConnection($accountId, ['enforce_sso' => true]);
        $this->addDomain($accountId, 'acme.test');

        return [$owner, $accountId, $connection];
    }

    private function invite(User $owner, int $accountId, string $email): User
    {
        $this->postJson('/users', [
            'first_name' => 'Invited',
            'last_name' => 'Person',
            'email' => $email,
            'role' => 'ORGANIZER',
        ], $this->bearer($this->tokenFor($owner, $accountId)))->assertCreated();

        return User::where('email', $email)->firstOrFail();
    }

    private function invitationToken(User $user, int $accountId): string
    {
        return app(EncryptedPayloadService::class)->encryptPayload([
            'user_id' => $user->id,
            'account_id' => $accountId,
        ]);
    }

    private function membershipStatus(User $user, int $accountId): string
    {
        return DB::table('account_users')
            ->where(['user_id' => $user->id, 'account_id' => $accountId])
            ->value('status');
    }
}
