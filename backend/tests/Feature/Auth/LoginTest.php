<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\Account;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_ROUTE = '/auth/login';

    private const LOGOUT_ROUTE = '/auth/logout';

    private const USERS_ME_ROUTE = '/users/me';

    protected function setUp(): void
    {
        parent::setUp();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => [
                'percentage' => 1.5,
                'fixed' => 0,
            ],
        ]);
    }

    public function test_login_with_valid_credentials(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();

        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertSuccessful();
        $response->assertCookie('token');
        $this->assertSame('lax', $response->getCookie('token', false)->getSameSite());
        $response->assertHeader('X-Auth-Token');
        $response->assertJsonStructure([
            'token',
            'token_type',
            'expires_in',
            'user',
            'accounts',
        ]);
    }

    public function test_login_from_a_cross_site_frontend_sets_a_cookie_the_browser_will_store(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();

        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => $password,
        ], ['Sec-Fetch-Site' => 'cross-site']);

        $response->assertSuccessful();
        $this->assertSame('none', $response->getCookie('token', false)->getSameSite());
        $this->assertTrue($response->getCookie('token', false)->isSecure());
    }

    public function test_login_with_invalid_credentials(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();

        $response = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => 'invalid_password',
        ]);

        $response->assertStatus(401);
        $response->assertCookieMissing('token');
        $response->assertHeaderMissing('X-Auth-Token');
    }

    public function test_logout(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();

        $response = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);
        $response->assertCookie('token');

        $response2 = $this->postJson(self::LOGOUT_ROUTE, [], [
            'Authorization' => 'Bearer '.$response->headers->get('X-Auth-Token'),
        ]);
        $response2->assertStatus(200);
        $response2->assertCookieExpired('token');

        // try to use the expired token
        $response3 = $this->getJson(self::USERS_ME_ROUTE, [
            'Authorization' => 'Bearer '.$response->headers->get('X-Auth-Token'),
        ]);
        $response3->assertStatus(401);
    }

    public function test_login_with_only_a_pending_invitation_tells_the_user_to_accept_it(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->create();
        $this->attachAccount($user, UserStatus::INVITED);

        $response = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'INVITATION_PENDING');
        $response->assertCookieMissing('token');
    }

    public function test_login_with_a_wrong_password_does_not_reveal_a_pending_invitation(): void
    {
        $user = User::factory()->password(fake()->password(16))->create();
        $this->attachAccount($user, UserStatus::INVITED);

        $response = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => 'invalid_password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonMissingPath('error_code');
    }

    public function test_login_with_an_inactive_membership_has_no_error_code(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->create();
        $this->attachAccount($user, UserStatus::INACTIVE);

        $response = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(401);
        $response->assertJsonMissingPath('error_code');
    }

    public function test_login_with_an_active_membership_and_a_pending_invitation_succeeds(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();
        $this->attachAccount($user, UserStatus::INVITED);

        $response = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertSuccessful();
        $response->assertCookie('token');
    }

    private function attachAccount(User $user, UserStatus $status): void
    {
        $user->accounts()->attach(Account::factory()->verified()->create(), [
            'role' => Role::ADMIN,
            'status' => $status,
            'is_account_owner' => false,
        ]);
    }
}
