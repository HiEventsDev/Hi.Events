<?php

namespace Tests\Feature\Auth;

use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResetPasswordErrorsTest extends TestCase
{
    use DatabaseTransactions;

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
    }

    public function test_reusing_the_current_password_is_reported_on_the_password_field(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();

        $this->postJson('/auth/reset-password/'.$this->resetToken($user), [
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'New password must be different from the old password.'])
            ->assertJsonMissingValidationErrors('current_password');
    }

    public function test_resetting_while_password_sign_in_is_turned_off_is_reported_on_the_password_field(): void
    {
        config()->set('sso.password_login_disabled', true);
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();

        $this->postJson('/auth/reset-password/'.$this->resetToken($user), [
            'password' => 'NewCorrectHorse123!',
            'password_confirmation' => 'NewCorrectHorse123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password')
            ->assertJsonMissingValidationErrors('current_password');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    private function resetToken(User $user): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }
}
