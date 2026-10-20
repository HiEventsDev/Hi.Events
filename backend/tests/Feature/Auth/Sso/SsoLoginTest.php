<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Mail\User\SignInMethodChangedMail;
use HiEvents\Models\Account;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Models\UserIdentity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\FakesOidcProvider;
use Tests\TestCase;

class SsoLoginTest extends TestCase
{
    use DatabaseTransactions;
    use FakesOidcProvider;

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
        config()->set('sso.providers.google.client_id');
        config()->set('sso.providers.microsoft.client_id');
        $this->fakeOidcProvider();
    }

    public function test_lists_only_configured_providers(): void
    {
        $this->getJson('/auth/sso/providers')
            ->assertOk()
            ->assertJsonCount(1, 'data.providers')
            ->assertJsonPath('data.providers.0.key', 'oidc')
            ->assertJsonPath('data.providers.0.name', 'Example SSO')
            ->assertJsonPath('data.password_login_enabled', true);
    }

    public function test_start_returns_an_authorization_url_with_pkce(): void
    {
        $flow = $this->startOidcFlow();

        $this->assertSame(self::OIDC_CLIENT_ID, $flow['query']['client_id']);
        $this->assertSame('S256', $flow['query']['code_challenge_method']);
        $this->assertSame(rtrim(config('app.frontend_url'), '/').'/sso/callback', $flow['query']['redirect_uri']);
        $this->assertNotEmpty($flow['query']['code_challenge']);
    }

    public function test_a_linked_identity_signs_in(): void
    {
        $user = User::factory()->withAccount()->create();
        $this->linkIdentity($user, 'subject-1');

        $response = $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-1', $user->email));

        $response->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED')
            ->assertCookie('token');
        $this->assertNotNull(DB::table('account_users')->where('user_id', $user->id)->value('last_login_at'));
        $this->assertSame('authorization-code', $this->lastTokenRequest['code']);
        $this->assertNotEmpty($this->lastTokenRequest['code_verifier']);
    }

    public function test_the_code_is_only_accepted_from_the_browser_that_started_the_flow(): void
    {
        $user = User::factory()->withAccount()->create();
        $this->linkIdentity($user, 'subject-1');
        $flow = $this->startOidcFlow();

        $this->exchangeOidcCode([...$flow, 'binding' => 'another-browser'], $this->claims('subject-1', $user->email))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_STATE_INVALID');
    }

    public function test_a_state_cannot_be_replayed(): void
    {
        $user = User::factory()->withAccount()->create();
        $this->linkIdentity($user, 'subject-1');
        $flow = $this->startOidcFlow();

        $this->exchangeOidcCode($flow, $this->claims('subject-1', $user->email))->assertOk();
        $this->exchangeOidcCode($flow, $this->claims('subject-1', $user->email))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_STATE_INVALID');
    }

    public function test_a_token_signed_for_another_nonce_is_rejected(): void
    {
        $user = User::factory()->withAccount()->create();
        $this->linkIdentity($user, 'subject-1');

        $this->exchangeOidcCode($this->startOidcFlow(), [...$this->claims('subject-1', $user->email), 'nonce' => 'other'])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_TOKEN_INVALID');
    }

    public function test_a_trusted_email_does_not_link_an_existing_account_on_a_self_hosted_install(): void
    {
        config()->set('app.saas_mode_enabled', false);
        $user = User::factory()->withAccount()->create();

        $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-1', $user->email))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LINK_REQUIRES_LOGIN')
            ->assertJsonPath('context.email', strtolower($user->email));
        $this->assertDatabaseMissing('user_identities', ['user_id' => $user->id]);
    }

    public function test_a_trusted_email_links_a_verified_account_in_saas_mode(): void
    {
        config()->set('app.saas_mode_enabled', true);
        $user = User::factory()->withAccount()->create();

        $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-1', strtoupper($user->email)))
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');

        $this->assertDatabaseHas('user_identities', ['user_id' => $user->id, 'subject' => 'subject-1', 'issuer' => self::OIDC_ISSUER]);
        Mail::assertQueued(SignInMethodChangedMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_an_unverified_account_is_never_linked_by_email(): void
    {
        config()->set('app.saas_mode_enabled', true);
        $user = User::factory()->unverified()->withAccount()->create();

        $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-1', $user->email))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LINK_REQUIRES_LOGIN');
    }

    public function test_an_untrusted_email_never_links_or_reveals_an_existing_account(): void
    {
        config()->set('app.saas_mode_enabled', true);
        $this->fakeOidcProvider(trustEmail: false);
        $user = User::factory()->withAccount()->create();

        $response = $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-1', $user->email));

        $response->assertConflict()->assertJsonPath('error_code', 'SSO_LINK_REQUIRES_LOGIN');
        $this->assertSame([], (array) $response->json('context'));
        $this->assertDatabaseMissing('user_identities', ['user_id' => $user->id]);
    }

    public function test_an_untrusted_email_does_not_reveal_whether_an_invitation_is_pending(): void
    {
        $this->fakeOidcProvider(trustEmail: false);
        $user = User::factory()->create(['password' => null]);
        $user->accounts()->attach(Account::factory()->verified()->create(), [
            'role' => Role::ORGANIZER->name,
            'status' => UserStatus::INVITED->name,
            'is_account_owner' => false,
        ]);

        $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-1', $user->email))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LINK_REQUIRES_LOGIN');
    }

    public function test_a_superadmin_is_never_linked_by_email(): void
    {
        config()->set('app.saas_mode_enabled', true);
        $user = User::factory()->create();
        $user->accounts()->attach(Account::factory()->verified()->create(), [
            'role' => Role::SUPERADMIN->name,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => true,
        ]);

        $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-1', $user->email))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LINK_REQUIRES_LOGIN');
    }

    public function test_an_invited_user_is_told_to_accept_the_invitation(): void
    {
        $user = User::factory()->create(['password' => null]);
        $user->accounts()->attach(Account::factory()->verified()->create(), [
            'role' => Role::ORGANIZER->name,
            'status' => UserStatus::INVITED->name,
            'is_account_owner' => false,
        ]);
        $this->linkIdentity($user, 'subject-1');

        $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-1', $user->email))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_INVITATION_PENDING');
    }

    public function test_a_user_with_two_factor_gets_a_challenge_bound_to_the_browser(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);
        $user = User::factory()->withTwoFactor($secret)->withAccount()->create();
        $this->linkIdentity($user, 'subject-1');
        $flow = $this->startOidcFlow();

        $response = $this->exchangeOidcCode($flow, $this->claims('subject-1', $user->email))
            ->assertOk()
            ->assertJsonPath('data.status', 'TWO_FACTOR_REQUIRED')
            ->assertCookieMissing('token');
        $challenge = $response->json('data.two_factor_challenge_token');

        $this->postJson('/auth/login/two-factor', [
            'challenge_token' => $challenge,
            'code' => $google2fa->getCurrentOtp($secret),
            'sso_binding' => 'another-browser',
        ])->assertUnauthorized();

        $this->postJson('/auth/login/two-factor', [
            'challenge_token' => $challenge,
            'code' => $google2fa->getCurrentOtp($secret),
            'sso_binding' => $flow['binding'],
        ])->assertOk()->assertCookie('token');
    }

    public function test_a_user_in_several_accounts_chooses_one_with_a_browser_bound_ticket(): void
    {
        $user = User::factory()->withAccount()->create();
        $secondAccount = Account::factory()->verified()->create();
        $user->accounts()->attach($secondAccount, [
            'role' => Role::ADMIN->name,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => false,
        ]);
        $inactiveAccount = Account::factory()->verified()->create();
        $user->accounts()->attach($inactiveAccount, [
            'role' => Role::ADMIN->name,
            'status' => UserStatus::INACTIVE->name,
            'is_account_owner' => false,
        ]);
        $this->linkIdentity($user, 'subject-1');
        $flow = $this->startOidcFlow();

        $response = $this->exchangeOidcCode($flow, $this->claims('subject-1', $user->email))
            ->assertOk()
            ->assertJsonPath('data.status', 'CHOOSE_ACCOUNT')
            ->assertJsonCount(2, 'data.accounts');
        $ticket = $response->json('data.login_ticket');

        $this->postJson('/auth/sso/complete', ['login_ticket' => $ticket, 'binding' => 'other', 'account_id' => $secondAccount->id])
            ->assertUnauthorized();
        $this->postJson('/auth/sso/complete', ['login_ticket' => $ticket, 'binding' => $flow['binding'], 'account_id' => $inactiveAccount->id])
            ->assertUnauthorized();
        $this->postJson('/auth/sso/complete', ['login_ticket' => $ticket, 'binding' => $flow['binding'], 'account_id' => $secondAccount->id])
            ->assertOk()
            ->assertCookie('token');
        $this->postJson('/auth/sso/complete', ['login_ticket' => $ticket, 'binding' => $flow['binding'], 'account_id' => $secondAccount->id])
            ->assertUnauthorized();
    }

    public function test_an_unknown_identity_must_sign_up(): void
    {
        $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-9', 'new.person@example.com'))
            ->assertOk()
            ->assertJsonPath('data.status', 'SIGNUP_REQUIRED')
            ->assertJsonPath('data.signup.email', 'new.person@example.com')
            ->assertJsonPath('data.signup.email_verified', true)
            ->assertJsonPath('data.signup.first_name', 'Ada');
    }

    public function test_registration_disabled_stops_new_sso_users(): void
    {
        config()->set('app.disable_registration', true);

        $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-9', 'new.person@example.com'))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REGISTRATION_DISABLED');
    }

    public function test_a_return_path_must_stay_on_this_site(): void
    {
        $user = User::factory()->withAccount()->create();
        $this->linkIdentity($user, 'subject-1');

        $this->exchangeOidcCode($this->startOidcFlow(['intent' => 'LOGIN', 'return_path' => '//evil.example.com']), $this->claims('subject-1', $user->email))
            ->assertJsonPath('data.return_path', null);
        $this->exchangeOidcCode($this->startOidcFlow(['intent' => 'LOGIN', 'return_path' => '/manage/events']), $this->claims('subject-1', $user->email))
            ->assertJsonPath('data.return_path', '/manage/events');
    }

    public function test_an_unconfigured_provider_cannot_start(): void
    {
        $this->postJson('/auth/sso/google/start', ['intent' => 'LOGIN'])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'SSO_PROVIDER_NOT_CONFIGURED');
    }

    public function test_an_email_shared_by_two_users_is_never_linked(): void
    {
        config()->set('app.saas_mode_enabled', true);
        User::factory()->withAccount()->create(['email' => 'shared@example.com']);
        User::factory()->withAccount()->create(['email' => 'Shared@Example.com']);

        $response = $this->exchangeOidcCode($this->startOidcFlow(), $this->claims('subject-1', 'shared@example.com'));

        $response->assertConflict()->assertJsonPath('error_code', 'SSO_LINK_REQUIRES_LOGIN');
        $this->assertDatabaseMissing('user_identities', ['subject' => 'subject-1']);
    }

    private function claims(string $subject, string $email): array
    {
        return [
            'sub' => $subject,
            'email' => $email,
            'email_verified' => true,
            'given_name' => 'Ada',
            'family_name' => 'Lovelace',
        ];
    }

    private function linkIdentity(User $user, string $subject): void
    {
        UserIdentity::create([
            'user_id' => $user->id,
            'provider_key' => 'oidc',
            'issuer' => self::OIDC_ISSUER,
            'subject' => $subject,
            'email' => $user->email,
        ]);
    }
}
