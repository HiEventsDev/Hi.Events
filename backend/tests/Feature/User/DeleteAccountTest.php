<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\Event;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_ROUTE = '/auth/login';
    private const USERS_ME_ROUTE = '/users/me';
    private const DELETE_ACCOUNT_ROUTE = '/settings/account';

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

    public function test_user_can_delete_self_with_correct_password_and_confirmation(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();
        $originalEmail = $user->email;

        $loginResponse = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $token = (string)$loginResponse->headers->get('X-Auth-Token');

        $deleteResponse = $this->deleteJson(self::DELETE_ACCOUNT_ROUTE, [
            'confirmation' => 'DELETE',
            'password' => $password,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $deleteResponse->assertStatus(200);
        $deleteResponse->assertCookieExpired('token');

        $deletedUser = User::withTrashed()->find($user->id);
        $this->assertNotNull($deletedUser);
        $this->assertNotNull($deletedUser->deleted_at);
        $this->assertNotSame($originalEmail, $deletedUser->email);

        // Token should no longer work
        $meResponse = $this->getJson(self::USERS_ME_ROUTE, [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $meResponse->assertStatus(401);
    }

    public function test_delete_fails_if_confirmation_word_missing_or_wrong(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();

        $loginResponse = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);
        $token = (string)$loginResponse->headers->get('X-Auth-Token');

        $deleteResponse = $this->deleteJson(self::DELETE_ACCOUNT_ROUTE, [
            'confirmation' => 'NOPE',
            'password' => $password,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $deleteResponse->assertStatus(422);
        $deleteResponse->assertJsonStructure(['message', 'errors' => ['confirmation']]);
    }

    public function test_delete_fails_if_password_incorrect(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();

        $loginResponse = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);
        $token = (string)$loginResponse->headers->get('X-Auth-Token');

        $deleteResponse = $this->deleteJson(self::DELETE_ACCOUNT_ROUTE, [
            'confirmation' => 'DELETE',
            'password' => 'wrong-password-123',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $deleteResponse->assertStatus(422);
        $deleteResponse->assertJsonStructure(['message', 'errors' => ['password']]);
    }

    public function test_delete_fails_if_user_is_sole_owner_with_other_members(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();
        $account = $user->accounts()->firstOrFail();

        $otherUser = User::factory()->withAccount()->create();
        // Attach other user to the same account (non-owner)
        $otherUser->accounts()->attach($account, [
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'is_account_owner' => false,
        ]);

        $loginResponse = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);
        $token = (string)$loginResponse->headers->get('X-Auth-Token');

        $deleteResponse = $this->deleteJson(self::DELETE_ACCOUNT_ROUTE, [
            'confirmation' => 'DELETE',
            'password' => $password,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $deleteResponse->assertStatus(409);
    }

    public function test_delete_fails_if_user_is_sole_owner_with_published_or_upcoming_events(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();
        $account = $user->accounts()->firstOrFail();

        Event::query()->create([
            'title' => 'Test Event',
            'account_id' => $account->id,
            'user_id' => $user->id,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(7)->addHours(2),
            'status' => EventStatus::LIVE->name,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
            'short_id' => Str::random(12),
        ]);

        $loginResponse = $this->postJson(self::LOGIN_ROUTE, [
            'email' => $user->email,
            'password' => $password,
        ]);
        $token = (string)$loginResponse->headers->get('X-Auth-Token');

        $deleteResponse = $this->deleteJson(self::DELETE_ACCOUNT_ROUTE, [
            'confirmation' => 'DELETE',
            'password' => $password,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $deleteResponse->assertStatus(409);
    }
}
