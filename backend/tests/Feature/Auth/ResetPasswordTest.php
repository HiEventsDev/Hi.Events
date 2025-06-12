<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use HiEvents\Mail\User\ForgotPassword;
use HiEvents\Mail\User\ResetPasswordSuccess;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use ReflectionClass;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const RESET_PASSWORD_ROUTE = '/auth/reset-password';
    private const FORGOT_PASSWORD_ROUTE = '/auth/forgot-password';

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

    public function test_forgot_password_with_valid_email(): void
    {
        $user = User::factory()->withAccount()->create();
        Mail::fake();

        $response = $this->postJson(self::FORGOT_PASSWORD_ROUTE, [
            'email' => $user->email,
        ]);

        $response->assertStatus(200);
        Mail::assertQueued(ForgotPassword::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_forgot_password_with_invalid_email(): void
    {
        User::factory()->withAccount()->create();
        Mail::fake();

        $response = $this->postJson(self::FORGOT_PASSWORD_ROUTE, [
            'email' => 'otheremail@example.com',
        ]);

        $response->assertStatus(200);
        Mail::assertNotSent(ForgotPassword::class);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = User::factory()->withAccount()->create();
        Mail::fake();

        $response = $this->postJson(self::FORGOT_PASSWORD_ROUTE, [
            'email' => $user->email,
        ]);
        $response->assertStatus(200);

        $mails = Mail::queued(ForgotPassword::class);
        $this->assertNotEmpty($mails);
        /** @var ForgotPassword $email */
        $email = $mails->last();

        // extract the token from the email
        $reflection = new ReflectionClass($email);
        $tokenProperty = $reflection->getProperty('token');
        $tokenProperty->setAccessible(true);
        $token = $tokenProperty->getValue($email);

        $response2 = $this->getJson(self::RESET_PASSWORD_ROUTE . '/' . urlencode($token));
        // assert token is valid
        $response2->assertStatus(204);

        $password = fake()->password(16);
        $response3 = $this->postJson(self::RESET_PASSWORD_ROUTE . '/' . urlencode($token), [
            'password' => $password,
            'password_confirmation' => $password,
        ]);
        $response3->assertStatus(200);

        Mail::assertQueued(ResetPasswordSuccess::class);
    }

    public function test_reset_password_with_invalid_token(): void
    {
        $user = User::factory()->withAccount()->create();
        Mail::fake();

        // create a token in database
        $response = $this->postJson(self::FORGOT_PASSWORD_ROUTE, [
            'email' => $user->email,
        ]);
        $response->assertStatus(200);

        $response2 = $this->getJson(self::RESET_PASSWORD_ROUTE . '/' . 'invalid_token');
        $response2->assertStatus(404);

        $password = fake()->password(16);

        $response3 = $this->postJson(self::RESET_PASSWORD_ROUTE . '/' . 'invalid_token', [
            'password' => $password,
            'password_confirmation' => $password,
        ]);
        $response3->assertStatus(404);

        Mail::assertNotQueued(ResetPasswordSuccess::class);
    }

    public function test_reset_password_with_old_password(): void
    {
        $password = fake()->password(16);
        $user = User::factory()->password($password)->withAccount()->create();
        Mail::fake();

        $response = $this->postJson(self::FORGOT_PASSWORD_ROUTE, [
            'email' => $user->email,
        ]);
        $response->assertStatus(200);

        $mails = Mail::queued(ForgotPassword::class);
        $this->assertNotEmpty($mails);
        /** @var ForgotPassword $email */
        $email = $mails->last();

        // extract the token from the email
        $reflection = new ReflectionClass($email);
        $tokenProperty = $reflection->getProperty('token');
        $tokenProperty->setAccessible(true);
        $token = $tokenProperty->getValue($email);

        $response2 = $this->postJson(self::RESET_PASSWORD_ROUTE . '/' . urlencode($token), [
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response2->assertJsonValidationErrors('current_password');
    }
}
