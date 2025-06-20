<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use HiEvents\Models\Account;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTER_ROUTE = '/auth/register';

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
            ],
        ]);
    }

    public function test_register_user(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->make([
            'email' => fake()->unique()->safeEmail()
        ]);
        $account = Account::factory()->make();

        Config::set('app.disable_registration', false);

        $response = $this->post(self::REGISTER_ROUTE, [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
            'timezone' => $user->timezone,
            'currency_code' => $account->currency_code,
            'locale' => $user->locale,
            'invite_token' => null,
        ]);

        $response->assertStatus(201);

        $userFromDB = User::where('email', $user->email)->first();
        $accountFromDB = Account::where('email', $user->email)->first();

        $this->assertNotNull($userFromDB);
        $this->assertNotNull($accountFromDB);

        $this->assertDatabaseHas('account_users', [
            'account_id' => $accountFromDB->id,
            'user_id' => $userFromDB->id,
        ]);

        // registered user got logged in
        $response->assertCookie('token');
        $response->assertHeader('X-Auth-Token');
    }

    public function test_registration_disabled(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->make();
        $account = Account::factory()->make();

        Config::set('app.disable_registration', true);

        $response = $this->post(self::REGISTER_ROUTE, [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
            'timezone' => $user->timezone,
            'currency_code' => $account->currency_code,
            'locale' => $user->locale,
            'invite_token' => null,
        ]);

        $response->assertStatus(403);
    }

    public function test_register_user_with_duplicate_data(): void
    {
        $user = User::factory()->make();
        $account = Account::factory()->make();
        $password = fake()->password(16);
        $data = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
            'timezone' => $user->timezone,
            'currency_code' => $account->currency_code,
            'locale' => $user->locale,
            'invite_token' => null,
        ];

        Config::set('app.disable_registration', false);

        $response = $this->post(self::REGISTER_ROUTE, $data);
        $response->assertStatus(201);

        Config::set('app.disable_registration', false);

        auth()->logout();

        $response2 = $this->post(self::REGISTER_ROUTE, $data, ['Accept' => 'application/json']);
        $response2->assertJsonValidationErrors(['email']);
    }

    public function test_register_user_with_invalid_data(): void
    {
        $user = User::factory()->make();
        $account = Account::factory()->make();
        $password = fake()->password(16);
        $data = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
            'timezone' => $user->timezone,
            'currency_code' => $account->currency_code,
            'locale' => $user->locale,
        ];
        Config::set('app.disable_registration', false);

        // valid currency code but not supported by application (north corean won)
        $response = $this->post(
            self::REGISTER_ROUTE,
            [...$data, 'currency_code' => 'KPW'],
            ['Accept' => 'application/json']
        );
        $response->assertJsonValidationErrors(['currency_code']);

        // valid locale but not supported by application (tigriyna)
        $response = $this->post(
            self::REGISTER_ROUTE,
            [...$data, 'locale' => 'ti_ER'],
            ['Accept' => 'application/json']
        );
        $response->assertJsonValidationErrors(['locale']);

        // invalid invite_token
        $response = $this->post(
            self::REGISTER_ROUTE,
            [...$data, 'invite_token' => 'aaaaaa'],
            ['Accept' => 'application/json']
        );
        $response->assertJsonValidationErrors(['invite_token']);
    }
}
