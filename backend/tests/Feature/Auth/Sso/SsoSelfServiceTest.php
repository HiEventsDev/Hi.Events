<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\SsoException;
use HiEvents\Mail\User\SignInMethodChangedMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Models\User;
use HiEvents\Models\UserIdentity;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoIdentityClaims;
use HiEvents\Services\Domain\Auth\Sso\UserIdentityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\FakesOidcProvider;
use Tests\TestCase;

class SsoSelfServiceTest extends TestCase
{
    use DatabaseTransactions;
    use FakesOidcProvider;

    private const PASSWORD = 'CorrectHorse123!';

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
        $this->fakeOidcProvider();
    }

    public function test_linking_requires_the_current_password(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->authed($token)->postJson('/users/me/sso-identities/oidc/start', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
        $this->authed($token)->postJson('/users/me/sso-identities/oidc/start')
            ->assertUnprocessable();
    }

    public function test_a_user_links_an_identity_from_the_same_session(): void
    {
        [$user, $token] = $this->signedInUser();

        $flow = $this->flowFrom($this->authed($token)
            ->postJson('/users/me/sso-identities/oidc/start', ['password' => self::PASSWORD, 'return_path' => '/settings/security'])
            ->assertOk()
            ->json('data'));

        $this->exchangeOidcCode($flow, ['sub' => 'personal', 'email' => 'somebody.else@example.com'], '/users/me/sso-identities/exchange', $this->headersFor($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'LINKED')
            ->assertJsonPath('data.return_path', '/settings/security');

        $this->assertDatabaseHas('user_identities', ['user_id' => $user->id, 'subject' => 'personal']);
        Mail::assertQueued(SignInMethodChangedMail::class);
        $this->authed($token)->getJson('/users/me/sso-identities')
            ->assertOk()
            ->assertJsonPath('data.0.provider_key', 'oidc')
            ->assertJsonPath('data.0.email', 'somebody.else@example.com');
    }

    public function test_a_link_cannot_be_finished_by_another_session(): void
    {
        [$user, $token] = $this->signedInUser();
        $otherSession = JWTAuth::claims(['account_id' => $user->accounts()->first()->id])->fromUser($user);

        $flow = $this->flowFrom($this->authed($token)
            ->postJson('/users/me/sso-identities/oidc/start', ['password' => self::PASSWORD])
            ->json('data'));

        $this->exchangeOidcCode($flow, ['sub' => 'personal'], '/users/me/sso-identities/exchange', $this->headersFor($otherSession))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_STATE_INVALID');
        $this->assertDatabaseMissing('user_identities', ['user_id' => $user->id]);
    }

    public function test_a_link_cannot_be_finished_on_the_public_exchange(): void
    {
        [, $token] = $this->signedInUser();

        $flow = $this->flowFrom($this->authed($token)
            ->postJson('/users/me/sso-identities/oidc/start', ['password' => self::PASSWORD])
            ->json('data'));

        $this->exchangeOidcCode($flow, ['sub' => 'personal'])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_STATE_INVALID');
    }

    public function test_an_identity_belonging_to_someone_else_cannot_be_linked(): void
    {
        $owner = User::factory()->withAccount()->create();
        $this->linkIdentity($owner, 'shared');
        [$user, $token] = $this->signedInUser();

        $flow = $this->flowFrom($this->authed($token)
            ->postJson('/users/me/sso-identities/oidc/start', ['password' => self::PASSWORD])
            ->json('data'));

        $this->exchangeOidcCode($flow, ['sub' => 'shared'], '/users/me/sso-identities/exchange', $this->headersFor($token))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_IDENTITY_IN_USE');
    }

    public function test_the_only_sign_in_method_cannot_be_removed(): void
    {
        [$user, $token] = $this->signedInUser(password: null);
        $identity = $this->linkIdentity($user, 'only');

        $this->authed($token)->deleteJson('/users/me/sso-identities/'.$identity->id)
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LAST_SIGN_IN_METHOD');
        $this->assertDatabaseHas('user_identities', ['id' => $identity->id]);
    }

    public function test_removing_an_identity_ends_other_sessions_but_keeps_this_one(): void
    {
        [$user, $token] = $this->signedInUser();
        $identity = $this->linkIdentity($user, 'personal');
        $otherSession = JWTAuth::claims(['account_id' => $user->accounts()->first()->id])->fromUser($user);
        $this->travel(2)->seconds();

        $response = $this->authed($token)->deleteJson('/users/me/sso-identities/'.$identity->id, ['password' => self::PASSWORD])
            ->assertOk();

        $this->assertDatabaseMissing('user_identities', ['id' => $identity->id]);
        Mail::assertQueued(SignInMethodChangedMail::class);
        $this->authed($otherSession)->getJson('/users/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SESSION_REVOKED');
        $this->authed($response->json('token'))->getJson('/users/me')->assertOk();
    }

    public function test_another_users_identity_cannot_be_removed(): void
    {
        $owner = User::factory()->withAccount()->create();
        $identity = $this->linkIdentity($owner, 'theirs');
        [, $token] = $this->signedInUser();

        $this->authed($token)->deleteJson('/users/me/sso-identities/'.$identity->id, ['password' => self::PASSWORD])
            ->assertNotFound();
        $this->assertDatabaseHas('user_identities', ['id' => $identity->id]);
    }

    public function test_a_passwordless_user_turns_on_two_factor_after_signing_in_again(): void
    {
        [$user, $token] = $this->signedInUser(password: null);
        $this->linkIdentity($user, 'sso-only');

        $this->authed($token)->postJson('/users/me/two-factor/setup')
            ->assertUnprocessable();

        $flow = $this->flowFrom($this->authed($token)
            ->postJson('/users/me/sso-identities/oidc/reauth', ['return_path' => '/settings/security'])
            ->assertOk()
            ->json('data'));
        $this->assertSame('login', $flow['query']['prompt']);
        $this->assertSame('0', $flow['query']['max_age']);

        $proof = $this->exchangeOidcCode($flow, ['sub' => 'sso-only', 'auth_time' => time()], '/users/me/sso-identities/exchange', $this->headersFor($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'REAUTHENTICATED')
            ->json('data.reauth_proof');

        $this->authed($token)->postJson('/users/me/two-factor/setup', ['reauth_proof' => $proof])
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret']]);
    }

    public function test_reauthenticating_with_an_unlinked_identity_fails(): void
    {
        [$user, $token] = $this->signedInUser(password: null);
        $this->linkIdentity($user, 'sso-only');

        $flow = $this->flowFrom($this->authed($token)->postJson('/users/me/sso-identities/oidc/reauth')->json('data'));

        $this->exchangeOidcCode($flow, ['sub' => 'attacker'], '/users/me/sso-identities/exchange', $this->headersFor($token))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_REAUTH_FAILED');
        $this->assertDatabaseMissing('user_identities', ['subject' => 'attacker']);
    }

    public function test_a_stale_sign_in_does_not_count_as_reauthentication(): void
    {
        [$user, $token] = $this->signedInUser(password: null);
        $this->linkIdentity($user, 'sso-only');

        $flow = $this->flowFrom($this->authed($token)->postJson('/users/me/sso-identities/oidc/reauth')->json('data'));

        $this->exchangeOidcCode($flow, ['sub' => 'sso-only', 'auth_time' => time() - 3600], '/users/me/sso-identities/exchange', $this->headersFor($token))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_REAUTH_FAILED');
    }

    public function test_a_passwordless_member_of_a_two_factor_account_can_still_reauthenticate(): void
    {
        [$user, $token] = $this->signedInUser(password: null);
        $user->accounts()->first()->update(['require_two_factor_authentication' => true]);
        $this->linkIdentity($user, 'sso-only');

        $this->authed($token)->postJson('/users/me/sso-identities/oidc/start')->assertForbidden();
        $this->authed($token)->getJson('/users/me/sso-identities')->assertOk();
        $this->authed($token)->postJson('/users/me/sso-identities/oidc/reauth')->assertOk();
    }

    public function test_turning_off_two_factor_still_needs_the_code_after_reauthenticating(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);
        $user = User::factory()->withTwoFactor($secret)->withAccount()->create(['password' => null]);
        $token = JWTAuth::claims(['account_id' => $user->accounts()->first()->id])->fromUser($user);
        $this->linkIdentity($user, 'sso-only');

        $flow = $this->flowFrom($this->authed($token)->postJson('/users/me/sso-identities/oidc/reauth')->json('data'));
        $proof = $this->exchangeOidcCode($flow, ['sub' => 'sso-only'], '/users/me/sso-identities/exchange', $this->headersFor($token))
            ->json('data.reauth_proof');

        $this->authed($token)->postJson('/users/me/two-factor/disable', ['reauth_proof' => $proof, 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->authed($token)->postJson('/users/me/two-factor/disable', ['reauth_proof' => $proof, 'code' => $google2fa->getCurrentOtp($secret)])
            ->assertNoContent();
    }

    public function test_sign_in_methods_cannot_be_changed_while_impersonating(): void
    {
        [$user] = $this->signedInUser();
        $token = JWTAuth::claims([
            'account_id' => $user->accounts()->first()->id,
            'is_impersonating' => true,
            'impersonator_id' => User::factory()->create()->id,
        ])->fromUser($user);

        $this->authed($token)->postJson('/users/me/sso-identities/oidc/reauth')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'IMPERSONATION_NOT_ALLOWED');
        $this->authed($token)->postJson('/users/me/sso-identities/oidc/start', ['password' => self::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'IMPERSONATION_NOT_ALLOWED');
    }

    public function test_an_identity_on_the_accounts_connection_counts_as_a_sign_in_method(): void
    {
        [$user, $token] = $this->signedInUser(password: null);
        $identity = $this->linkIdentity($user, 'social');
        $connection = AccountSsoConnection::create([
            'account_id' => $user->accounts()->first()->id,
            'type' => 'OKTA',
            'issuer' => 'https://acme.okta.test',
            'client_id' => 'acme-client',
            'client_secret' => 'connection-secret',
            'status' => 'ACTIVE',
            'session_ttl_minutes' => 720,
            'jit_provisioning_enabled' => false,
            'jit_default_role' => 'ORGANIZER',
        ]);
        UserIdentity::create([
            'user_id' => $user->id,
            'provider_key' => 'connection-'.$connection->id,
            'account_sso_connection_id' => $connection->id,
            'issuer' => 'https://acme.okta.test',
            'subject' => 'okta-user',
        ]);

        $this->authed($token)->deleteJson('/users/me/sso-identities/'.$identity->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $connection->update(['status' => 'INACTIVE']);

        $this->authed($token)->deleteJson('/users/me/sso-identities/'.$identity->id)
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LAST_SIGN_IN_METHOD');
    }

    public function test_an_identity_on_a_connection_of_an_account_the_user_left_does_not_count(): void
    {
        [$user, $token] = $this->signedInUser(password: null);
        $identity = $this->linkIdentity($user, 'social');
        $otherAccountId = User::factory()->withAccount()->create()->accounts()->first()->id;
        $connection = AccountSsoConnection::create([
            'account_id' => $otherAccountId,
            'type' => 'OKTA',
            'issuer' => 'https://acme.okta.test',
            'client_id' => 'acme-client',
            'client_secret' => 'connection-secret',
            'status' => 'ACTIVE',
            'session_ttl_minutes' => 720,
            'jit_provisioning_enabled' => false,
            'jit_default_role' => 'ORGANIZER',
        ]);
        UserIdentity::create([
            'user_id' => $user->id,
            'provider_key' => 'connection-'.$connection->id,
            'account_sso_connection_id' => $connection->id,
            'issuer' => 'https://acme.okta.test',
            'subject' => 'okta-user',
        ]);

        $this->authed($token)->getJson('/users/me/sso-identities')
            ->assertOk()
            ->assertJsonPath('data.1.organization_provider', null);

        $this->authed($token)->deleteJson('/users/me/sso-identities/'.$identity->id)
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LAST_SIGN_IN_METHOD');
    }

    public function test_a_login_on_a_removed_provider_does_not_count_as_a_sign_in_method(): void
    {
        [$user, $token] = $this->signedInUser(password: null);
        $identity = $this->linkIdentity($user, 'usable');
        UserIdentity::create([
            'user_id' => $user->id,
            'provider_key' => 'removed-provider',
            'issuer' => 'https://removed.example.test',
            'subject' => 'gone',
        ]);

        $this->authed($token)->deleteJson('/users/me/sso-identities/'.$identity->id)
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LAST_SIGN_IN_METHOD');
    }

    public function test_an_identity_owned_by_someone_else_never_resets_the_signing_in_user(): void
    {
        config()->set('sso.password_login_disabled', true);
        $owner = User::factory()->withAccount()->create();
        $this->linkIdentity($owner, 'taken');
        $other = User::factory()->password(self::PASSWORD)->withAccount()->create();

        try {
            app(UserIdentityService::class)->attachForSignIn(
                UserDomainObject::hydrateFromModel($other),
                new SsoIdentityClaims('oidc', self::OIDC_ISSUER, 'taken', null, false, null, null, null),
            );
            $this->fail('Expected the identity to be refused');
        } catch (SsoException $exception) {
            $this->assertSame(SsoErrorCode::IDENTITY_IN_USE, $exception->errorCode);
        }

        $this->assertNotNull($other->fresh()->password);
    }

    private function authed(string $token): static
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return $this->withToken($token);
    }

    private function headersFor(string $token): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function signedInUser(?string $password = self::PASSWORD): array
    {
        $user = User::factory()->withAccount()->create(['password' => $password !== null ? bcrypt($password) : null]);

        return [$user, JWTAuth::claims(['account_id' => $user->accounts()->first()->id])->fromUser($user)];
    }

    private function linkIdentity(User $user, string $subject): UserIdentity
    {
        return UserIdentity::create([
            'user_id' => $user->id,
            'provider_key' => 'oidc',
            'issuer' => self::OIDC_ISSUER,
            'subject' => $subject,
            'email' => $user->email,
        ]);
    }
}
