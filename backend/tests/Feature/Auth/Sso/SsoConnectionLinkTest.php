<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Enterprise\Sso\Mail\SsoBreakGlassSignInMail;
use HiEvents\Mail\User\SignInMethodChangedMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Models\User;
use HiEvents\Models\UserIdentity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CreatesSsoConnections;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\TestCase;

class SsoConnectionLinkTest extends TestCase
{
    use ActsAsAccountUsers;
    use CreatesSsoConnections;
    use DatabaseTransactions;
    use ManagesFeatureFlags;

    private const PASSWORD = 'CorrectHorse123!';

    private string $ownerSecret;

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
        $this->ownerSecret = (new Google2FA)->generateSecretKey(32);
    }

    public function test_the_owner_links_the_connection_and_signs_in_through_it_without_an_emergency_sign_in_email(): void
    {
        [$owner, $accountId, $connection] = $this->enforcingAccount();
        $this->member($accountId, 'admin@acme.test', enforced: true, role: Role::ADMIN);

        $this->linkConnection($owner, $accountId, $connection, 'okta-owner')
            ->assertOk()
            ->assertJsonPath('data.status', 'LINKED')
            ->assertJsonPath('data.provider_name', 'Okta')
            ->assertJsonPath('data.return_path', '/settings/security');

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $owner->id,
            'provider_key' => 'connection-'.$connection->id,
            'account_sso_connection_id' => $connection->id,
            'issuer' => self::CONNECTION_ISSUER,
            'subject' => 'okta-owner',
        ]);
        Mail::assertQueued(SignInMethodChangedMail::class, fn (SignInMethodChangedMail $mail) => $mail->hasTo($owner->email));

        $flow = $this->startConnectionFlow($connection);
        $challenge = $this->exchangeOidcCode($flow, $this->connectionClaims('okta-owner', 'it-lead@elsewhere.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'TWO_FACTOR_REQUIRED')
            ->json('data.two_factor_challenge_token');

        $token = $this->postJson('/auth/login/two-factor', [
            'challenge_token' => $challenge,
            'code' => (new Google2FA)->getCurrentOtp($this->ownerSecret),
            'sso_binding' => $flow['binding'],
        ])->assertOk()->json('token');

        $payload = $this->decodeToken($token);
        $this->assertSame($accountId, $payload['account_id']);
        $this->assertSame($connection->id, $payload['sso_connection_id']);
        Mail::assertNotQueued(SsoBreakGlassSignInMail::class);
        $this->getJson('/users/me', $this->bearer($token))->assertOk();
    }

    public function test_a_linked_owner_is_never_enforced_and_their_password_sign_in_still_emails_every_admin(): void
    {
        [$owner, $accountId, $connection] = $this->enforcingAccount();
        $admin = $this->member($accountId, 'admin@acme.test', enforced: true, role: Role::ADMIN);
        $this->linkConnection($owner, $accountId, $connection, 'okta-owner')->assertOk();

        $members = collect($this->getJson("/accounts/$accountId/sso/enforcement", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->json('data.members'))->keyBy('email');
        $this->assertTrue($members[$owner->email]['sso_available']);
        $this->assertFalse($members[$owner->email]['sso_enforced']);

        $challenge = $this->postJson('/auth/login', ['email' => $owner->email, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJson(['two_factor_required' => true])
            ->json('two_factor_challenge_token');

        $this->postJson('/auth/login/two-factor', [
            'challenge_token' => $challenge,
            'code' => (new Google2FA)->getCurrentOtp($this->ownerSecret),
        ])->assertOk()->assertJsonStructure(['token']);

        Mail::assertQueued(SsoBreakGlassSignInMail::class, fn (SsoBreakGlassSignInMail $mail) => $mail->hasTo($admin->email));
        Mail::assertQueued(SsoBreakGlassSignInMail::class, fn (SsoBreakGlassSignInMail $mail) => $mail->hasTo($owner->email));
    }

    public function test_a_member_links_the_connection_without_gaining_access_or_changing_role(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount(enabled: false);
        $member = $this->member($accountId, 'grace@elsewhere.test', role: Role::ORGANIZER);

        $this->linkConnection($member, $accountId, $connection, 'okta-grace', 'grace@acme.test')
            ->assertOk()
            ->assertJsonPath('data.status', 'LINKED');

        $memberships = DB::table('account_users')->where('user_id', $member->id)->get();
        $this->assertCount(1, $memberships);
        $this->assertSame(Role::ORGANIZER->value, $memberships->first()->role);
        $this->assertFalse((bool) $memberships->first()->sso_enforced);
        $this->assertSame('grace@elsewhere.test', $member->fresh()->email);

        $token = $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-grace', 'grace@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED')
            ->json('data.token');
        $this->assertSame($member->id, (int) $this->decodeToken($token)['sub']);
    }

    public function test_someone_outside_the_account_cannot_link_its_connection(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        $outsider = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $outsiderAccountId = $outsider->accounts()->first()->id;

        $this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/start', ['password' => self::PASSWORD], $this->headersFor($outsider, $outsiderAccountId))
            ->assertNotFound()
            ->assertJsonPath('error_code', 'SSO_PROVIDER_NOT_CONFIGURED');

        $invited = $this->member($accountId, 'invited@acme.test', status: UserStatus::INVITED);
        $invited->accounts()->attach($outsiderAccountId, ['role' => Role::ORGANIZER->value, 'status' => UserStatus::ACTIVE->name, 'is_account_owner' => false]);
        $this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/start', ['password' => self::PASSWORD], $this->headersFor($invited, $outsiderAccountId))
            ->assertNotFound()
            ->assertJsonPath('error_code', 'SSO_PROVIDER_NOT_CONFIGURED');

        $this->assertDatabaseMissing('user_identities', ['account_sso_connection_id' => $connection->id]);
    }

    public function test_a_member_of_another_account_can_link_only_that_accounts_connection(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        [, $otherAccountId] = $this->createAccountOwner();
        $otherConnection = $this->createConnection($otherAccountId);
        $member = $this->member($otherAccountId, 'grace@other.test');

        $this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/start', ['password' => self::PASSWORD], $this->headersFor($member, $otherAccountId))
            ->assertNotFound()
            ->assertJsonPath('error_code', 'SSO_PROVIDER_NOT_CONFIGURED');

        $this->postJson('/users/me/sso-identities/connection-'.$otherConnection->id.'/start', ['password' => self::PASSWORD], $this->headersFor($member, $otherAccountId))
            ->assertOk();
    }

    public function test_membership_is_checked_again_when_the_link_finishes(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        $member = $this->member($accountId, 'grace@acme.test');
        $otherAccountId = User::factory()->withAccount()->create()->accounts()->first()->id;
        $member->accounts()->attach($otherAccountId, ['role' => Role::ORGANIZER->value, 'status' => UserStatus::ACTIVE->name, 'is_account_owner' => false]);
        $headers = $this->headersFor($member, $otherAccountId);
        $flow = $this->flowFrom($this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/start', ['password' => self::PASSWORD], $headers)
            ->assertOk()
            ->json('data'));

        DB::table('account_users')->where(['user_id' => $member->id, 'account_id' => $accountId])->update(['status' => UserStatus::INACTIVE->name]);

        $this->exchangeOidcCode($flow, $this->connectionClaims('okta-grace', 'grace@acme.test'), '/users/me/sso-identities/exchange', $headers)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'SSO_PROVIDER_NOT_CONFIGURED');

        $this->assertDatabaseMissing('user_identities', ['user_id' => $member->id]);
    }

    public function test_an_identity_that_belongs_to_someone_else_cannot_be_linked(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        $first = $this->member($accountId, 'first@acme.test');
        $second = $this->member($accountId, 'second@acme.test');
        $this->linkConnection($first, $accountId, $connection, 'okta-shared')->assertOk();

        $this->linkConnection($second, $accountId, $connection, 'okta-shared')
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_IDENTITY_IN_USE');

        $this->assertDatabaseMissing('user_identities', ['user_id' => $second->id]);
    }

    public function test_a_connection_that_is_turned_off_cannot_be_linked(): void
    {
        [$owner, $accountId, $connection] = $this->enforcingAccount(enabled: false);
        $connection->update(['status' => 'INACTIVE']);

        $this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/start', ['password' => self::PASSWORD], $this->headersFor($owner, $accountId))
            ->assertNotFound()
            ->assertJsonPath('error_code', 'SSO_PROVIDER_NOT_CONFIGURED');
    }

    public function test_a_connection_cannot_be_linked_while_impersonating(): void
    {
        [$owner, $accountId, $connection] = $this->enforcingAccount(enabled: false);
        $impersonation = JWTAuth::claims([
            'account_id' => $accountId,
            'is_impersonating' => true,
            'impersonator_id' => User::factory()->create()->id,
        ])->fromUser($owner);

        $this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/start', ['password' => self::PASSWORD], $this->bearer($impersonation))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'IMPERSONATION_NOT_ALLOWED');
    }

    public function test_the_owner_unlinks_the_connection_and_can_no_longer_sign_in_through_it(): void
    {
        [$owner, $accountId, $connection] = $this->enforcingAccount();
        $this->linkConnection($owner, $accountId, $connection, 'okta-owner')->assertOk();
        $identity = UserIdentity::where(['user_id' => $owner->id, 'subject' => 'okta-owner'])->firstOrFail();

        $this->deleteJson('/users/me/sso-identities/'.$identity->id, ['password' => self::PASSWORD], $this->headersFor($owner, $accountId))
            ->assertOk();

        $this->assertDatabaseMissing('user_identities', ['id' => $identity->id]);
        Mail::assertQueued(SignInMethodChangedMail::class, 2);

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-owner', $owner->email))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LINK_REQUIRES_LOGIN');
    }

    public function test_a_member_who_owns_another_account_is_enforced_once_they_link_the_connection(): void
    {
        [$owner, $accountId, $connection] = $this->enforcingAccount();
        $alice = $this->member($accountId, 'alice@acme.test', enforced: true);
        $aliceAccountId = User::factory()->withAccount()->create()->accounts()->first()->id;
        $alice->accounts()->attach($aliceAccountId, [
            'role' => Role::ADMIN->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => true,
        ]);

        $this->assertFalse($this->previewFor($owner, $accountId)['alice@acme.test']['sso_available']);
        $this->postJson('/auth/login', ['email' => 'alice@acme.test', 'password' => self::PASSWORD, 'account_id' => $accountId])->assertOk();

        $this->linkConnection($alice, $accountId, $connection, 'okta-alice')->assertOk();

        $this->assertTrue($this->previewFor($owner, $accountId)['alice@acme.test']['sso_available']);
        $this->postJson('/auth/login', ['email' => 'alice@acme.test', 'password' => self::PASSWORD, 'account_id' => $accountId])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED');
        $this->postJson('/auth/login', ['email' => 'alice@acme.test', 'password' => self::PASSWORD, 'account_id' => $aliceAccountId])->assertOk();
        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-alice', 'alice@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');
    }

    public function test_sign_in_methods_offer_the_connections_of_accounts_the_user_is_active_in(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount(enabled: false);
        DB::table('accounts')->where('id', $accountId)->update(['name' => 'Acme Events']);
        $member = $this->member($accountId, 'grace@acme.test');
        [, $inactiveAccountId] = $this->createAccountOwner();
        $this->createConnection($inactiveAccountId, ['status' => 'INACTIVE', 'issuer' => 'https://inactive.okta.test']);
        $member->accounts()->attach($inactiveAccountId, ['role' => Role::ORGANIZER->value, 'status' => UserStatus::ACTIVE->name, 'is_account_owner' => false]);
        [, $invitedAccountId] = $this->createAccountOwner();
        $this->createConnection($invitedAccountId, ['issuer' => 'https://invited.okta.test']);
        $member->accounts()->attach($invitedAccountId, ['role' => Role::ORGANIZER->value, 'status' => UserStatus::INVITED->name, 'is_account_owner' => false]);

        $this->getJson('/users/me/sso-identities', $this->headersFor($member, $accountId))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonCount(1, 'meta.linkable_connections')
            ->assertJsonPath('meta.linkable_connections.0', [
                'key' => 'connection-'.$connection->id,
                'name' => 'Okta',
                'type' => 'OIDC',
                'logo_url' => null,
                'account_name' => 'Acme Events',
            ]);
    }

    public function test_a_superadmin_cannot_link_a_connection_or_see_it_offered(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount(enabled: false);
        $staff = $this->member($accountId, 'staff@acme.test', role: Role::SUPERADMIN);

        $this->getJson('/users/me/sso-identities', $this->headersFor($staff, $accountId))
            ->assertOk()
            ->assertJsonCount(0, 'meta.linkable_connections');

        $this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/start', ['password' => self::PASSWORD], $this->headersFor($staff, $accountId))
            ->assertNotFound()
            ->assertJsonPath('error_code', 'SSO_PROVIDER_NOT_CONFIGURED');

        $this->assertDatabaseMissing('user_identities', ['user_id' => $staff->id]);
    }

    public function test_a_member_made_superadmin_during_a_link_is_refused_when_it_finishes(): void
    {
        [, $accountId, $connection] = $this->enforcingAccount();
        $member = $this->member($accountId, 'grace@acme.test');
        $headers = $this->headersFor($member, $accountId);
        $flow = $this->flowFrom($this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/start', ['password' => self::PASSWORD], $headers)
            ->assertOk()
            ->json('data'));

        DB::table('account_users')->where('user_id', $member->id)->update(['role' => Role::SUPERADMIN->name]);

        $this->exchangeOidcCode($flow, $this->connectionClaims('okta-grace', 'grace@acme.test'), '/users/me/sso-identities/exchange', $headers)
            ->assertNotFound()
            ->assertJsonPath('error_code', 'SSO_PROVIDER_NOT_CONFIGURED');

        $this->assertDatabaseMissing('user_identities', ['user_id' => $member->id]);
    }

    private function linkConnection(User $user, int $accountId, AccountSsoConnection $connection, string $subject, ?string $email = null): TestResponse
    {
        $headers = $this->headersFor($user, $accountId);
        $flow = $this->flowFrom($this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/start', [
            'password' => self::PASSWORD,
            'return_path' => '/settings/security',
        ], $headers)->assertOk()->json('data'));

        return $this->exchangeOidcCode($flow, $this->connectionClaims($subject, $email ?? $user->email), '/users/me/sso-identities/exchange', $headers);
    }

    private function previewFor(User $owner, int $accountId): array
    {
        return collect($this->getJson("/accounts/$accountId/sso/enforcement", $this->headersFor($owner, $accountId))
            ->assertOk()
            ->json('data.members'))->keyBy('email')->all();
    }

    /**
     * @return array{0: User, 1: int, 2: AccountSsoConnection}
     */
    private function enforcingAccount(bool $enabled = true): array
    {
        $owner = User::factory()->password(self::PASSWORD)->withTwoFactor($this->ownerSecret)->withAccount()->create();
        $accountId = $owner->accounts()->first()->id;
        $connection = $this->createConnection($accountId, ['enforce_sso' => $enabled]);
        $this->addDomain($accountId, 'acme.test');

        return [$owner, $accountId, $connection];
    }

    private function member(int $accountId, string $email, bool $enforced = false, Role $role = Role::ORGANIZER, UserStatus $status = UserStatus::ACTIVE): User
    {
        $user = User::factory()->password(self::PASSWORD)->create(['email' => $email]);
        $user->accounts()->attach($accountId, [
            'role' => $role->value,
            'status' => $status->name,
            'is_account_owner' => false,
            'sso_enforced' => $enforced,
        ]);

        return $user;
    }
}
