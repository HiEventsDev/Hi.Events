<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Mail\User\TwoFactorDisabledMail;
use HiEvents\Mail\User\TwoFactorEnabledMail;
use HiEvents\Mail\User\TwoFactorRecoveryCodeUsedMail;
use HiEvents\Models\Account;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorVerifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'CorrectHorse123!';

    private const RECOVERY_CODES = ['abcde-fghjk', 'mnpqr-stuvw'];

    private Google2FA $google2fa;

    private string $secret;

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

        $this->google2fa = new Google2FA;
        $this->secret = $this->google2fa->generateSecretKey(32);
    }

    public function test_login_returns_a_challenge_instead_of_a_token_when_two_factor_is_enabled(): void
    {
        $user = $this->createTwoFactorUser();

        $response = $this->login($user);

        $response->assertOk();
        $response->assertJson(['token' => null, 'two_factor_required' => true]);
        $response->assertJsonStructure(['two_factor_challenge_token', 'expires_in']);
        $response->assertCookieMissing('token');
        $response->assertHeaderMissing('X-Auth-Token');
    }

    public function test_valid_code_completes_the_login(): void
    {
        $user = $this->createTwoFactorUser();
        $challenge = $this->login($user)->json('two_factor_challenge_token');

        $response = $this->postJson(route('auth.login.two-factor'), [
            'challenge_token' => $challenge,
            'code' => $this->currentCode(),
        ]);

        $response->assertOk();
        $response->assertCookie('token');
        $response->assertCookieMissing(TrustedDeviceService::COOKIE_NAME);
        $this->assertNotEmpty($response->json('token'));
        $this->assertNotNull(DB::table('account_users')->where('user_id', $user->id)->value('last_login_at'));
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $user = $this->createTwoFactorUser();
        $code = $this->currentCode();

        $this->postJson(route('auth.login.two-factor'), [
            'challenge_token' => $this->login($user)->json('two_factor_challenge_token'),
            'code' => $code,
        ])->assertOk();

        $this->postJson(route('auth.login.two-factor'), [
            'challenge_token' => $this->login($user)->json('two_factor_challenge_token'),
            'code' => $code,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_too_many_wrong_codes_lock_the_user_out_across_new_challenges(): void
    {
        $user = $this->createTwoFactorUser();
        $firstChallenge = $this->login($user)->json('two_factor_challenge_token');

        for ($i = 0; $i < TwoFactorVerifier::MAX_FAILED_ATTEMPTS; $i++) {
            $this->postJson(route('auth.login.two-factor'), [
                'challenge_token' => $firstChallenge,
                'code' => '000000',
            ])->assertUnprocessable();
        }

        $this->postJson(route('auth.login.two-factor'), [
            'challenge_token' => $this->login($user)->json('two_factor_challenge_token'),
            'code' => $this->currentCode(),
        ])->assertUnauthorized()->assertJson(['error_code' => 'TWO_FACTOR_CHALLENGE_EXPIRED']);
    }

    public function test_setup_requires_the_current_password(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();

        $this->postJson('/users/me/two-factor/setup', ['password' => 'not-my-password'], $this->authHeaders($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_inactive_members_are_rejected_before_the_code_is_asked_for(): void
    {
        $user = $this->createTwoFactorUser();
        $user->accounts()->updateExistingPivot($user->accounts()->first()->id, ['status' => UserStatus::INACTIVE->name]);

        $this->login($user)->assertUnauthorized()->assertJsonMissing(['two_factor_required' => true]);
    }

    public function test_recovery_code_logs_in_once_and_notifies_the_user(): void
    {
        $user = $this->createTwoFactorUser();

        $response = $this->postJson(route('auth.login.two-factor'), [
            'challenge_token' => $this->login($user)->json('two_factor_challenge_token'),
            'recovery_code' => ' ABCDE FGHJK ',
        ]);

        $response->assertOk();
        $response->assertJson(['two_factor_recovery_codes_remaining' => 1]);
        Mail::assertQueued(TwoFactorRecoveryCodeUsedMail::class);

        $this->postJson(route('auth.login.two-factor'), [
            'challenge_token' => $this->login($user)->json('two_factor_challenge_token'),
            'recovery_code' => 'abcde-fghjk',
        ])->assertUnprocessable()->assertJsonValidationErrors('recovery_code');
    }

    public function test_trusted_device_skips_the_challenge_until_the_password_changes(): void
    {
        $user = $this->createTwoFactorUser();

        $verifyResponse = $this->postJson(route('auth.login.two-factor'), [
            'challenge_token' => $this->login($user)->json('two_factor_challenge_token'),
            'code' => $this->currentCode(),
            'remember_device' => true,
        ]);
        $verifyResponse->assertOk();
        $deviceCookie = $verifyResponse->getCookie(TrustedDeviceService::COOKIE_NAME, false);
        $this->assertNotNull($deviceCookie);
        $this->assertTrue($deviceCookie->isHttpOnly());
        $this->assertTrue($deviceCookie->isSecure());

        $trustedLogin = $this->withCredentials()->withUnencryptedCookie(TrustedDeviceService::COOKIE_NAME, $deviceCookie->getValue())
            ->postJson(route('auth.login'), ['email' => $user->email, 'password' => self::PASSWORD]);
        $trustedLogin->assertOk();
        $this->assertNotEmpty($trustedLogin->json('token'));

        $this->resetAuth();
        $this->putJson('/users/me', [
            'current_password' => self::PASSWORD,
            'password' => 'AnotherHorse456!',
            'password_confirmation' => 'AnotherHorse456!',
        ], ['Authorization' => 'Bearer '.$trustedLogin->json('token')])->assertOk();

        $this->resetAuth();
        $this->withCredentials()->withUnencryptedCookie(TrustedDeviceService::COOKIE_NAME, $deviceCookie->getValue())
            ->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'AnotherHorse456!'])
            ->assertOk()
            ->assertJson(['two_factor_required' => true]);
    }

    public function test_trusted_device_cookie_does_not_work_for_another_user(): void
    {
        $user = $this->createTwoFactorUser();
        $otherUser = $this->createTwoFactorUser();

        $deviceCookie = $this->postJson(route('auth.login.two-factor'), [
            'challenge_token' => $this->login($user)->json('two_factor_challenge_token'),
            'code' => $this->currentCode(),
            'remember_device' => true,
        ])->getCookie(TrustedDeviceService::COOKIE_NAME, false);

        $this->withCredentials()->withUnencryptedCookie(TrustedDeviceService::COOKIE_NAME, $deviceCookie->getValue())
            ->postJson(route('auth.login'), ['email' => $otherUser->email, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJson(['two_factor_required' => true]);
    }

    public function test_users_with_multiple_accounts_choose_an_account_after_verifying_once(): void
    {
        $user = $this->createTwoFactorUser();
        $secondAccount = Account::factory()->verified()->create();
        $user->accounts()->attach($secondAccount, [
            'role' => Role::ORGANIZER,
            'status' => UserStatus::ACTIVE,
            'is_account_owner' => false,
        ]);
        $challenge = $this->login($user)->json('two_factor_challenge_token');

        $verifyResponse = $this->postJson(route('auth.login.two-factor'), [
            'challenge_token' => $challenge,
            'code' => $this->currentCode(),
        ]);
        $verifyResponse->assertOk();
        $this->assertNull($verifyResponse->json('token'));
        $this->assertCount(2, $verifyResponse->json('accounts'));

        $selectResponse = $this->postJson(route('auth.login.two-factor'), [
            'challenge_token' => $challenge,
            'account_id' => $secondAccount->id,
        ]);
        $selectResponse->assertOk();
        $this->assertNotEmpty($selectResponse->json('token'));
        $selectResponse->assertCookie('token');
    }

    public function test_setup_confirm_and_disable_through_the_api(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $headers = $this->authHeaders($user);

        $setup = $this->postJson('/users/me/two-factor/setup', ['password' => self::PASSWORD], $headers);
        $setup->assertOk();
        $secret = $setup->json('data.secret');
        $this->assertStringStartsWith('otpauth://totp/', $setup->json('data.otpauth_uri'));

        $this->postJson('/users/me/two-factor/confirm', ['code' => '000000'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $confirm = $this->postJson('/users/me/two-factor/confirm', [
            'code' => $this->google2fa->getCurrentOtp($secret),
        ], $headers);
        $confirm->assertOk();
        $this->assertCount(10, $confirm->json('data.recovery_codes'));
        Mail::assertQueued(TwoFactorEnabledMail::class);

        $this->getJson('/users/me/two-factor', $headers)
            ->assertOk()
            ->assertJson(['data' => ['enabled' => true, 'recovery_codes_remaining' => 10]]);

        $this->postJson('/users/me/two-factor/setup', ['password' => self::PASSWORD], $headers)->assertStatus(409);

        $this->postJson('/users/me/two-factor/disable', [
            'password' => 'wrong-password',
            'recovery_code' => $confirm->json('data.recovery_codes.0'),
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/users/me/two-factor/disable', [
            'password' => self::PASSWORD,
            'recovery_code' => $confirm->json('data.recovery_codes.0'),
        ], $headers)->assertNoContent();
        Mail::assertQueued(TwoFactorDisabledMail::class);

        $this->login($user)->assertOk()->assertJsonMissing(['two_factor_required' => true]);
    }

    public function test_account_requirement_blocks_members_until_they_enrol(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $user->accounts()->first()->update(['require_two_factor_authentication' => true]);
        $headers = $this->authHeaders($user);

        $this->getJson('/organizers', $headers)
            ->assertForbidden()
            ->assertJson(['error_code' => 'TWO_FACTOR_SETUP_REQUIRED']);

        $this->getJson('/users/me', $headers)
            ->assertOk()
            ->assertJson(['data' => ['two_factor_setup_required' => true]]);

        $setup = $this->postJson('/users/me/two-factor/setup', ['password' => self::PASSWORD], $headers)->assertOk();
        $this->postJson('/users/me/two-factor/confirm', [
            'code' => $this->google2fa->getCurrentOtp($setup->json('data.secret')),
        ], $headers)->assertOk();

        $this->resetAuth();
        $this->getJson('/organizers', $headers)->assertOk();
    }

    public function test_two_factor_cannot_be_disabled_while_an_account_requires_it(): void
    {
        $user = $this->createTwoFactorUser();
        $user->accounts()->first()->update(['require_two_factor_authentication' => true]);

        $this->postJson('/users/me/two-factor/disable', [
            'password' => self::PASSWORD,
            'recovery_code' => self::RECOVERY_CODES[0],
        ], $this->authHeaders($user))->assertStatus(409);
    }

    public function test_admin_must_enrol_before_requiring_two_factor_for_the_account(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $this->putJson("/accounts/$accountId/two-factor-requirement", [
            'require_two_factor_authentication' => true,
        ], $this->authHeaders($user))->assertStatus(409);

        $enrolledUser = $this->createTwoFactorUser();
        $enrolledAccountId = $enrolledUser->accounts()->first()->id;

        $this->putJson("/accounts/$enrolledAccountId/two-factor-requirement", [
            'require_two_factor_authentication' => true,
        ], $this->authHeaders($enrolledUser))
            ->assertOk()
            ->assertJson(['data' => ['require_two_factor_authentication' => true]]);
    }

    public function test_organizers_cannot_change_the_account_requirement(): void
    {
        $user = $this->createTwoFactorUser();
        $account = $user->accounts()->first();
        $user->accounts()->updateExistingPivot($account->id, ['role' => Role::ORGANIZER->name]);

        $this->putJson("/accounts/{$account->id}/two-factor-requirement", [
            'require_two_factor_authentication' => true,
        ], $this->authHeaders($user))->assertForbidden();
    }

    public function test_only_superadmins_can_reset_another_users_two_factor(): void
    {
        $target = $this->createTwoFactorUser();
        $admin = User::factory()->password(self::PASSWORD)->withAccount()->create();

        $this->postJson("/admin/users/{$target->id}/two-factor/reset", [], $this->authHeaders($admin))
            ->assertForbidden();

        $admin->accounts()->updateExistingPivot($admin->accounts()->first()->id, ['role' => Role::SUPERADMIN->name]);

        $this->postJson("/admin/users/{$target->id}/two-factor/reset", [], $this->authHeaders($admin))
            ->assertNoContent();
        Mail::assertQueued(TwoFactorDisabledMail::class);

        $this->assertNull($target->fresh()->two_factor_secret);
    }

    public function test_account_admin_can_reset_a_members_two_factor(): void
    {
        $owner = $this->createTwoFactorUser();
        $account = $owner->accounts()->first();
        $member = $this->createMemberOf($account, Role::ORGANIZER);

        $this->postJson("/users/{$member->id}/two-factor/reset", [], $this->authHeaders($owner))
            ->assertNoContent();

        Mail::assertQueued(TwoFactorDisabledMail::class);
        $this->assertNull($member->fresh()->two_factor_secret);
    }

    public function test_organizers_cannot_reset_other_members(): void
    {
        $owner = $this->createTwoFactorUser();
        $account = $owner->accounts()->first();
        $organizer = $this->createMemberOf($account, Role::ORGANIZER);

        $this->postJson("/users/{$owner->id}/two-factor/reset", [], $this->authHeaders($organizer))
            ->assertForbidden();

        $this->assertNotNull($owner->fresh()->two_factor_secret);
    }

    public function test_non_owner_admins_cannot_reset_the_account_owner(): void
    {
        $owner = $this->createTwoFactorUser();
        $account = $owner->accounts()->first();
        $admin = $this->createMemberOf($account, Role::ADMIN);

        $this->postJson("/users/{$owner->id}/two-factor/reset", [], $this->authHeaders($admin))
            ->assertForbidden();
    }

    public function test_admins_cannot_reset_users_outside_their_account_or_themselves(): void
    {
        $owner = $this->createTwoFactorUser();
        $stranger = $this->createTwoFactorUser();

        $this->postJson("/users/{$stranger->id}/two-factor/reset", [], $this->authHeaders($owner))
            ->assertNotFound();

        $this->postJson("/users/{$owner->id}/two-factor/reset", [], $this->authHeaders($owner))
            ->assertForbidden();

        $this->assertNotNull($stranger->fresh()->two_factor_secret);
    }

    public function test_account_admins_cannot_reset_users_who_belong_to_other_accounts(): void
    {
        $owner = $this->createTwoFactorUser();
        $member = $this->createMemberOf($owner->accounts()->first(), Role::ORGANIZER);
        $member->accounts()->attach(Account::factory()->verified()->create(), [
            'role' => Role::ADMIN->name,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => true,
        ]);

        $this->postJson("/users/{$member->id}/two-factor/reset", [], $this->authHeaders($owner))
            ->assertForbidden();

        $this->assertNotNull($member->fresh()->two_factor_secret);
    }

    private function createMemberOf(Account $account, Role $role): User
    {
        $member = User::factory()
            ->password(self::PASSWORD)
            ->withTwoFactor($this->secret, self::RECOVERY_CODES)
            ->create();
        $member->accounts()->attach($account, [
            'role' => $role->name,
            'status' => UserStatus::ACTIVE->name,
            'is_account_owner' => false,
        ]);

        return $member;
    }

    private function createTwoFactorUser(): User
    {
        return User::factory()
            ->password(self::PASSWORD)
            ->withTwoFactor($this->secret, self::RECOVERY_CODES)
            ->withAccount()
            ->create();
    }

    private function login(User $user): TestResponse
    {
        $this->resetAuth();

        return $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);
    }

    private function currentCode(): string
    {
        return $this->google2fa->getCurrentOtp($this->secret);
    }

    private function authHeaders(User $user): array
    {
        $this->resetAuth();
        $account = $user->accounts()->first();
        $token = auth('api')->claims([
            'account_id' => $account->id,
            'role' => $account->pivot->role instanceof Role ? $account->pivot->role->value : $account->pivot->role,
        ])->login($user);
        $this->resetAuth();

        return ['Authorization' => 'Bearer '.$token];
    }

    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
    }
}
