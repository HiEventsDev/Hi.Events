<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Mail\Account\AdminAccessGrantedMail;
use HiEvents\Mail\User\SignInMethodChangedMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Models\UserIdentity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CreatesSsoConnections;
use Tests\TestCase;

class EnterpriseSsoLoginTest extends TestCase
{
    use ActsAsAccountUsers;
    use CreatesSsoConnections;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);
        $this->fakeConnectionIssuer();
    }

    public function test_discovery_finds_the_connection_for_a_verified_domain_only(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId);
        $this->addDomain($accountId, 'acme.test');
        $this->addDomain($accountId, 'pending.test', verified: false);

        $this->postJson('/auth/sso/discover', ['email' => 'Ada@ACME.test'])
            ->assertOk()
            ->assertJsonPath('data.key', 'connection-'.$connection->id)
            ->assertJsonPath('data.name', 'Okta');

        foreach (['ada@pending.test', 'ada@unknown.test', 'ada@sub.acme.test'] as $email) {
            $this->postJson('/auth/sso/discover', ['email' => $email])
                ->assertNotFound()
                ->assertJsonPath('error_code', 'SSO_NOT_SET_UP');
        }

        $connection->update(['status' => 'INACTIVE']);
        $this->postJson('/auth/sso/discover', ['email' => 'ada@acme.test'])->assertNotFound();
    }

    public function test_discovery_stops_once_a_domain_has_failed_its_check_for_a_week(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $this->createConnection($accountId);
        $this->addDomain($accountId, 'acme.test', failingSince: now()->subDays(8)->toDateTimeString());

        $this->postJson('/auth/sso/discover', ['email' => 'ada@acme.test'])->assertNotFound();
    }

    public function test_a_member_signs_in_through_the_connection_with_a_session_that_cannot_be_refreshed(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId, ['session_ttl_minutes' => 60]);
        $this->addDomain($accountId, 'acme.test');
        $member = $this->addAccountUser($accountId);
        $member->update(['email' => 'grace@acme.test']);

        $flow = $this->startConnectionFlow($connection, ['intent' => 'LOGIN', 'login_hint' => 'grace@acme.test']);
        $this->assertSame('grace@acme.test', $flow['query']['login_hint']);
        $this->assertSame(self::CONNECTION_CLIENT_ID, $flow['query']['client_id']);

        $response = $this->exchangeOidcCode($flow, $this->connectionClaims('okta-grace', 'grace@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');

        $payload = $this->decodeToken($response->json('data.token'));
        $this->assertSame($connection->id, $payload['sso_connection_id']);
        $this->assertSame($accountId, $payload['account_id']);
        $this->assertEqualsWithDelta(60 * 60, $payload['exp'] - $payload['iat'], 5);
        $this->assertDatabaseHas('user_identities', [
            'user_id' => $member->id,
            'subject' => 'okta-grace',
            'account_sso_connection_id' => $connection->id,
        ]);
        $this->assertSame('client_secret_post', isset($this->lastTokenRequest['client_secret']) ? 'client_secret_post' : 'basic');

        $this->postJson('/auth/refresh', [], $this->bearer($response->json('data.token')))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_SESSION_EXPIRED');
    }

    public function test_a_new_person_on_a_verified_domain_is_provisioned_when_jit_is_on(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId, ['jit_provisioning_enabled' => true, 'jit_default_role' => 'VIEWER']);
        $this->addDomain($accountId, 'acme.test');

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-new', 'New.Person@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');

        $user = User::where('email', 'new.person@acme.test')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Ada', $user->first_name);
        $this->assertDatabaseHas('account_users', [
            'account_id' => $accountId,
            'user_id' => $user->id,
            'role' => 'VIEWER',
            'status' => UserStatus::ACTIVE->name,
            'access_scope' => 'ACCOUNT',
        ]);
        Mail::assertNotQueued(AdminAccessGrantedMail::class);
        Mail::assertNotQueued(SignInMethodChangedMail::class);
    }

    public function test_a_person_provisioned_as_an_admin_is_reported_to_the_owner(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId, ['jit_provisioning_enabled' => true, 'jit_default_role' => 'ADMIN']);
        $this->addDomain($accountId, 'acme.test');

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-admin', 'boss@acme.test'))->assertOk();

        Mail::assertQueued(AdminAccessGrantedMail::class, fn (AdminAccessGrantedMail $mail) => $mail->hasTo($owner->email));
    }

    public function test_nobody_is_provisioned_when_jit_is_off_or_the_email_is_not_trusted(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId);
        $this->addDomain($accountId, 'acme.test');

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-1', 'one@acme.test'))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_NO_ACTIVE_ACCOUNT');

        $connection->update(['jit_provisioning_enabled' => true]);

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-2', 'two@acme.test', ['email_verified' => false]))
            ->assertJsonPath('error_code', 'SSO_NO_ACTIVE_ACCOUNT');
        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-3', 'three@elsewhere.test'))
            ->assertJsonPath('error_code', 'SSO_NO_ACTIVE_ACCOUNT');

        $this->assertDatabaseMissing('users', ['email' => 'one@acme.test']);
        $this->assertDatabaseMissing('users', ['email' => 'two@acme.test']);
        $this->assertDatabaseMissing('users', ['email' => 'three@elsewhere.test']);
    }

    public function test_an_identity_provider_cannot_claim_an_existing_account_outside_its_verified_domains(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId, ['jit_provisioning_enabled' => true]);
        $this->addDomain($accountId, 'acme.test');
        [$victim] = $this->createAccountOwner();

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-evil', $victim->email))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LINK_REQUIRES_LOGIN');

        $this->assertDatabaseMissing('user_identities', ['user_id' => $victim->id]);
    }

    public function test_an_existing_user_on_a_verified_domain_is_linked_and_added_to_the_account(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId, ['jit_provisioning_enabled' => true]);
        $this->addDomain($accountId, 'acme.test');
        $existing = User::factory()->create(['email' => 'existing@acme.test']);

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-existing', 'existing@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');

        $this->assertDatabaseHas('user_identities', ['user_id' => $existing->id, 'subject' => 'okta-existing']);
        $this->assertDatabaseHas('account_users', ['account_id' => $accountId, 'user_id' => $existing->id, 'status' => 'ACTIVE']);
    }

    public function test_removed_and_deactivated_members_are_never_brought_back(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId, ['jit_provisioning_enabled' => true]);
        $this->addDomain($accountId, 'acme.test');

        $inactive = $this->addAccountUser($accountId, Role::ORGANIZER, UserStatus::INACTIVE);
        $inactive->update(['email' => 'inactive@acme.test']);
        $removed = $this->addAccountUser($accountId);
        $removed->update(['email' => 'removed@acme.test']);
        DB::table('account_users')->where(['account_id' => $accountId, 'user_id' => $removed->id])->update(['deleted_at' => now()]);

        foreach (['inactive@acme.test' => $inactive, 'removed@acme.test' => $removed] as $email => $user) {
            $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-'.$user->id, $email))
                ->assertConflict()
                ->assertJsonPath('error_code', 'SSO_NO_ACTIVE_ACCOUNT');
        }

        $this->assertSame('INACTIVE', DB::table('account_users')->where(['account_id' => $accountId, 'user_id' => $inactive->id])->value('status'));
        $this->assertNotNull(DB::table('account_users')->where(['account_id' => $accountId, 'user_id' => $removed->id])->value('deleted_at'));
    }

    public function test_a_member_who_was_removed_and_invited_again_can_sign_in(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId);
        $this->addDomain($accountId, 'acme.test');
        $member = $this->addAccountUser($accountId);
        $member->update(['email' => 'back@acme.test']);
        DB::table('account_users')->where(['account_id' => $accountId, 'user_id' => $member->id])->update(['deleted_at' => now()]);
        $member->accounts()->attach($accountId, [
            'role' => Role::ADMIN->value,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => false,
        ]);

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-back', 'back@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');
    }

    public function test_a_pending_invitation_on_a_verified_domain_is_accepted_by_signing_in(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId);
        $this->addDomain($accountId, 'acme.test');
        $invited = $this->addAccountUser($accountId, Role::ORGANIZER, UserStatus::INVITED);
        $invited->update(['email' => 'invited@acme.test', 'email_verified_at' => null]);

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-invited', 'invited@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');

        $this->assertSame('ACTIVE', DB::table('account_users')->where(['account_id' => $accountId, 'user_id' => $invited->id])->value('status'));
        $this->assertNotNull($invited->fresh()->email_verified_at);
    }

    public function test_a_token_issued_for_another_client_is_rejected(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId);

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-1', 'one@acme.test', ['aud' => 'another-client']))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_TOKEN_INVALID');
    }

    public function test_a_connection_cannot_be_used_to_sign_up_or_by_another_issuer_identity(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId);

        $this->postJson('/auth/sso/connection-'.$connection->id.'/start', ['intent' => 'SIGNUP'])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'SSO_PROVIDER_NOT_CONFIGURED');

        $connection->update(['status' => 'INACTIVE']);
        $this->postJson('/auth/sso/connection-'.$connection->id.'/start', ['intent' => 'LOGIN'])->assertNotFound();
    }

    public function test_a_connection_whose_issuer_resolves_to_a_private_address_is_never_contacted(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId);
        $this->fakeDnsAddresses['acme.okta.test'] = ['169.254.169.254'];

        $this->postJson('/auth/sso/connection-'.$connection->id.'/start', ['intent' => 'LOGIN'])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'SSO_ISSUER_NOT_ALLOWED');
    }

    public function test_an_identity_linked_through_the_connection_signs_in_even_after_an_email_change(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId);
        $member = $this->addAccountUser($accountId);
        UserIdentity::create([
            'user_id' => $member->id,
            'account_sso_connection_id' => $connection->id,
            'provider_key' => 'connection-'.$connection->id,
            'issuer' => self::CONNECTION_ISSUER,
            'subject' => 'okta-member',
        ]);

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-member', 'renamed@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');
    }

    public function test_a_member_who_only_uses_the_connection_can_confirm_their_identity_with_it(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId, ['jit_provisioning_enabled' => true]);
        $this->addDomain($accountId, 'acme.test');

        $token = $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-solo', 'solo@acme.test'))
            ->json('data.token');
        DB::table('user_identities')->where('subject', 'okta-solo')->update(['created_at' => now()->subDays(2)]);

        $this->getJson('/users/me/sso-identities', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.0.organization_provider.key', 'connection-'.$connection->id)
            ->assertJsonPath('data.0.organization_provider.name', 'Okta');

        $flow = $this->flowFrom($this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/reauth', [], $this->bearer($token))
            ->assertOk()
            ->json('data'));
        $this->assertSame('login', $flow['query']['prompt']);

        $this->exchangeOidcCode($flow, $this->connectionClaims('okta-solo', 'solo@acme.test', ['auth_time' => time()]), '/users/me/sso-identities/exchange', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'REAUTHENTICATED');
    }

    public function test_an_account_owner_is_never_linked_by_email_through_a_connection(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId, ['jit_provisioning_enabled' => true]);
        $this->addDomain($accountId, 'acme.test');
        [$owner] = $this->createAccountOwner();
        $owner->update(['email' => 'founder@acme.test']);

        $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('rogue-idp', 'founder@acme.test'))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LINK_REQUIRES_LOGIN');

        $this->assertDatabaseMissing('user_identities', ['user_id' => $owner->id]);
    }

    public function test_a_member_of_other_accounts_cannot_confirm_their_identity_through_a_connection(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId);
        $this->addDomain($accountId, 'acme.test');
        [, $otherAccountId] = $this->createAccountOwner();
        $member = $this->addAccountUser($accountId);
        $member->update(['email' => 'shared@acme.test']);
        $member->accounts()->attach($otherAccountId, ['role' => 'ORGANIZER', 'status' => 'ACTIVE', 'is_account_owner' => false]);

        $token = $this->exchangeOidcCode($this->startConnectionFlow($connection), $this->connectionClaims('okta-shared', 'shared@acme.test'))
            ->assertOk()
            ->json('data.token');

        $flow = $this->flowFrom($this->postJson('/users/me/sso-identities/connection-'.$connection->id.'/reauth', [], $this->bearer($token))->json('data'));

        $this->exchangeOidcCode($flow, $this->connectionClaims('okta-shared', 'shared@acme.test', ['auth_time' => time()]), '/users/me/sso-identities/exchange', $this->bearer($token))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_REAUTH_FAILED');
    }

    public function test_a_connection_sign_in_that_needs_two_factor_stays_in_the_connections_account(): void
    {
        $secret = (new Google2FA)->generateSecretKey(32);
        [, $accountId] = $this->createAccountOwner();
        $connection = $this->createConnection($accountId);
        $this->addDomain($accountId, 'acme.test');
        [, $otherAccountId] = $this->createAccountOwner();
        $member = User::factory()->withTwoFactor($secret)->create(['email' => 'two@acme.test']);
        $member->accounts()->attach($accountId, ['role' => 'ORGANIZER', 'status' => 'ACTIVE', 'is_account_owner' => false]);
        $member->accounts()->attach($otherAccountId, ['role' => 'ORGANIZER', 'status' => 'ACTIVE', 'is_account_owner' => false]);
        $flow = $this->startConnectionFlow($connection);

        $challenge = $this->exchangeOidcCode($flow, $this->connectionClaims('okta-two', 'two@acme.test'))
            ->assertOk()
            ->assertJsonPath('data.status', 'TWO_FACTOR_REQUIRED')
            ->json('data.two_factor_challenge_token');

        $token = $this->postJson('/auth/login/two-factor', [
            'challenge_token' => $challenge,
            'code' => (new Google2FA)->getCurrentOtp($secret),
            'sso_binding' => $flow['binding'],
        ])->assertOk()->json('token');

        $payload = $this->decodeToken($token);
        $this->assertSame($accountId, $payload['account_id']);
        $this->assertSame($connection->id, $payload['sso_connection_id']);
    }
}
