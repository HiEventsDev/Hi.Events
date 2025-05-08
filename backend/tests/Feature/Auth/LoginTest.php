<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use DatabaseTruncation;

    private const API_PREFIX = '';
    private const LOGIN_ROUTE = self::API_PREFIX . '/auth/login';
    private const LOGOUT_ROUTE = self::API_PREFIX . '/auth/logout';
    private const USERS_ME_ROUTE = self::API_PREFIX . '/users/me';

    public function setUp(): void
    {
        parent::setUp();
        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => json_encode(['percentage' => 1.5, 'fixed' => 0]),
        ]);
    }

    public function test_login_with_valid_credentials(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();

        $response = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertCookie('token');
        $response->assertHeader('X-Auth-Token');
        $response->assertJsonStructure([
            'token',
            'token_type',
            'expires_in',
            'user',
            'accounts'
        ]);

        // removes warning "This test did not perform any assertions"
        $this->assertTrue(true);
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
