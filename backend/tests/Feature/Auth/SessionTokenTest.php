<?php

namespace Tests\Feature\Auth;

use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Models\UserIdentity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class SessionTokenTest extends TestCase
{
    use ActsAsAccountUsers;
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

    public function test_a_refreshed_token_keeps_the_signed_in_account(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $token = $this->postJson('/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])->json('token');

        $refreshed = $this->withToken($token)->postJson('/auth/refresh')->assertOk()->json('token');

        $this->resetAuth();
        $this->withToken($refreshed)->getJson('/accounts/'.$user->accounts()->first()->id)->assertOk();
    }

    public function test_resetting_a_password_ends_every_session(): void
    {
        $user = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $token = $this->postJson('/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])->json('token');
        $this->travel(2)->seconds();

        $this->postJson('/auth/forgot-password', ['email' => $user->email]);
        $resetToken = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->postJson('/auth/reset-password/'.$resetToken, [
            'password' => 'NewCorrectHorse123!',
            'password_confirmation' => 'NewCorrectHorse123!',
        ])->assertOk();

        $this->resetAuth();
        $this->withToken($token)->getJson('/users/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SESSION_REVOKED');
    }

    public function test_resetting_the_password_of_an_unverified_user_removes_identities_someone_else_may_have_added(): void
    {
        $user = User::factory()->unverified()->withAccount()->create();
        UserIdentity::create([
            'user_id' => $user->id,
            'provider_key' => 'oidc',
            'issuer' => 'https://idp.example.test',
            'subject' => 'planted',
        ]);

        $this->postJson('/auth/forgot-password', ['email' => $user->email]);
        $resetToken = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->postJson('/auth/reset-password/'.$resetToken, [
            'password' => 'NewCorrectHorse123!',
            'password_confirmation' => 'NewCorrectHorse123!',
        ])->assertOk();

        $this->assertDatabaseMissing('user_identities', ['subject' => 'planted']);
    }

    public function test_a_user_without_a_password_cannot_sign_in_with_one(): void
    {
        $user = User::factory()->withAccount()->create(['password' => null]);

        $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'anything-at-all'])
            ->assertUnauthorized();
    }

    public function test_an_impersonation_session_ends_when_the_impersonator_is_signed_out_everywhere(): void
    {
        $impersonator = User::factory()->create();
        $user = User::factory()->withAccount()->create();
        $token = JWTAuth::claims([
            'account_id' => $user->accounts()->first()->id,
            'is_impersonating' => true,
            'impersonator_id' => $impersonator->id,
        ])->fromUser($user);

        $this->withToken($token)->getJson('/users/me')->assertOk();

        $this->travel(2)->seconds();
        $impersonator->forceFill(['tokens_valid_after' => now()])->save();

        $this->resetAuth();
        $this->withToken($token)->getJson('/users/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SESSION_REVOKED');
    }

    public function test_a_revoked_token_no_longer_previews_draft_events(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEvent($accountId, $this->createOrganizer($accountId), 'Draft');
        DB::table('events')->where('id', $eventId)->update(['status' => 'DRAFT']);
        $token = $this->tokenFor($owner, $accountId);

        $this->withToken($token)->getJson("/public/events/{$eventId}")->assertOk();

        $this->travel(2)->seconds();
        $owner->forceFill(['tokens_valid_after' => now()])->save();

        $this->resetAuth();
        $this->withToken($token)->getJson("/public/events/{$eventId}")->assertNotFound();
    }

    public function test_a_token_is_only_accepted_from_the_header_or_cookie(): void
    {
        $user = User::factory()->withAccount()->create();
        $token = $this->tokenFor($user, $user->accounts()->first()->id);

        $this->resetAuth();
        $this->getJson('/users/me?token='.$token)->assertUnauthorized();
        $this->resetAuth();
        $this->postJson('/users/me/sso-identities/exchange', ['token' => $token, 'code' => 'x', 'state' => 'y', 'binding' => 'z'])->assertUnauthorized();
    }

    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
    }
}
