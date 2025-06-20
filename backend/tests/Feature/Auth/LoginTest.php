<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

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

    public function setUp(): void
    {
        parent::setUp();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => [
                'percentage' => 1.5,
                'fixed' => 0,
            ]
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
        $response->assertHeader('X-Auth-Token');
        $response->assertJsonStructure([
            'token',
            'token_type',
            'expires_in',
            'user',
            'accounts'
        ]);
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
            'Authorization' => 'Bearer ' . $response->headers->get('X-Auth-Token'),
        ]);
        $response2->assertStatus(200);
        $response2->assertCookieExpired('token');

        // try to use the expired token
        $response3 = $this->getJson(self::USERS_ME_ROUTE, [
            'Authorization' => 'Bearer ' . $response->headers->get('X-Auth-Token'),
        ]);
        $response3->assertStatus(401);
    }
}
