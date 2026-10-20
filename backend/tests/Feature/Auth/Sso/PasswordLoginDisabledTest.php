<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Mail\User\ForgotPassword;
use HiEvents\Models\Account;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Models\UserIdentity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\FakesOidcProvider;
use Tests\TestCase;

class PasswordLoginDisabledTest extends TestCase
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
        config()->set('sso.password_login_disabled', true);
        config()->set('app.saas_mode_enabled', false);
        $this->fakeOidcProvider();
    }

    public function test_providers_report_that_password_sign_in_is_off(): void
    {
        $this->getJson('/auth/sso/providers')->assertJsonPath('data.password_login_enabled', false);
    }

    public function test_a_member_cannot_sign_in_with_a_password(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();

        $this->postJson('/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Username or Password are incorrect');
    }

    public function test_a_superadmin_keeps_password_sign_in(): void
    {
        $user = User::factory()->password(self::PASSWORD)->create();
        $user->accounts()->attach(Account::factory()->verified()->create(), [
            'role' => Role::SUPERADMIN->name,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => true,
        ]);

        $this->postJson('/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertCookie('token');
    }

    public function test_password_sign_up_is_refused(): void
    {
        $this->postJson('/auth/register', [
            'first_name' => 'Pat',
            'email' => 'pat@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'timezone' => 'UTC',
            'currency_code' => 'EUR',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'pat@example.com']);
    }

    public function test_forgot_password_sends_nothing_to_a_member(): void
    {
        $user = User::factory()->withAccount()->create();

        $this->postJson('/auth/forgot-password', ['email' => $user->email])->assertOk();

        Mail::assertNotQueued(ForgotPassword::class);
    }

    public function test_an_existing_member_is_linked_by_a_trusted_email_so_they_are_not_locked_out(): void
    {
        $user = User::factory()->withAccount()->create();

        $this->exchangeOidcCode($this->startOidcFlow(), ['sub' => 'member', 'email' => $user->email, 'email_verified' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTHENTICATED');

        $this->assertDatabaseHas('user_identities', ['user_id' => $user->id, 'subject' => 'member']);
    }

    public function test_the_first_link_by_email_removes_credentials_someone_else_may_have_set(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $staleToken = JWTAuth::claims(['account_id' => $user->accounts()->first()->id])->fromUser($user);
        $this->travel(2)->seconds();

        DB::table('user_trusted_devices')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'device'),
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $newToken = $this->exchangeOidcCode($this->startOidcFlow(), ['sub' => 'member', 'email' => $user->email, 'email_verified' => true])
            ->assertOk()
            ->json('data.token');

        $this->assertNull($user->fresh()->password);
        $this->assertDatabaseMissing('user_trusted_devices', ['user_id' => $user->id]);
        $this->resetAuth();
        $this->withToken($staleToken)->getJson('/users/me')->assertUnauthorized();
        $this->resetAuth();
        $this->withToken($newToken)->getJson('/users/me')->assertOk();
    }

    public function test_a_password_that_can_no_longer_be_used_does_not_count_as_a_sign_in_method(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $identity = UserIdentity::create([
            'user_id' => $user->id,
            'provider_key' => 'oidc',
            'issuer' => self::OIDC_ISSUER,
            'subject' => 'only',
        ]);
        $token = JWTAuth::claims(['account_id' => $user->accounts()->first()->id])->fromUser($user);

        $this->withToken($token)->deleteJson('/users/me/sso-identities/'.$identity->id, ['password' => self::PASSWORD])
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_LAST_SIGN_IN_METHOD');
    }

    public function test_a_password_that_can_no_longer_sign_in_cannot_confirm_identity(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $token = JWTAuth::claims(['account_id' => $user->accounts()->first()->id])->fromUser($user);

        $this->withToken($token)->getJson('/users/me')->assertJsonPath('data.has_password', false);

        $this->withToken($token)->putJson('/users/me', [
            'email' => 'moved-'.$user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'timezone' => 'UTC',
            'locale' => 'en',
            'confirmation_password' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('confirmation_password');

        $this->assertNull($user->fresh()->pending_email);
    }

    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
    }
}
