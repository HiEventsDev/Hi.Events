<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\Mail\Account\EmailConfirmationCodeEmail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\FakesOidcProvider;
use Tests\TestCase;

class SsoSignupTest extends TestCase
{
    use DatabaseTransactions;
    use FakesOidcProvider;

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
        config()->set('app.saas_mode_enabled', true);
        $this->fakeOidcProvider();
    }

    public function test_a_trusted_identity_creates_a_verified_passwordless_account(): void
    {
        [$flow, $ticket] = $this->signupTicket('grace@example.com');

        $response = $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket, [
            'utm_source' => 'Google',
            'utm_medium' => 'cpc',
        ]));

        $response->assertOk()->assertCookie('token');
        $user = User::where('email', 'grace@example.com')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $account = $user->accounts()->firstOrFail();
        $this->assertNotNull($account->account_verified_at);
        $this->assertTrue((bool) $account->pivot->is_account_owner);
        $this->assertDatabaseHas('user_identities', ['user_id' => $user->id, 'subject' => 'new-subject']);
        $this->assertDatabaseHas('account_attributions', ['account_id' => $account->id, 'utm_source' => 'google']);
        Mail::assertNothingQueued();
    }

    public function test_a_signup_ticket_only_works_in_the_browser_that_started_the_flow(): void
    {
        [$flow, $ticket] = $this->signupTicket('grace@example.com');

        $this->postJson('/auth/sso/signup', $this->signupPayload([...$flow, 'binding' => 'other'], $ticket))
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_TICKET_INVALID');
        $this->assertDatabaseMissing('users', ['email' => 'grace@example.com']);
    }

    public function test_a_signup_ticket_cannot_be_used_twice(): void
    {
        [$flow, $ticket] = $this->signupTicket('grace@example.com');

        $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket))->assertOk();
        $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket))->assertUnauthorized();
        $this->assertSame(1, User::where('email', 'grace@example.com')->count());
    }

    public function test_signup_fails_when_the_email_was_registered_after_the_ticket_was_issued(): void
    {
        [$flow, $ticket] = $this->signupTicket('grace@example.com');
        User::factory()->withAccount()->create(['email' => 'grace@example.com']);

        $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket))
            ->assertConflict()
            ->assertJsonPath('error_code', 'SSO_EMAIL_TAKEN');
        $this->assertDatabaseMissing('user_identities', ['subject' => 'new-subject']);
    }

    public function test_an_untrusted_email_must_be_confirmed_with_a_code(): void
    {
        $this->fakeOidcProvider(trustEmail: false);
        [$flow, $ticket] = $this->signupTicket('linus@example.com', emailVerified: false);

        $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket))
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'SSO_EMAIL_CODE_INVALID');

        $this->postJson('/auth/sso/signup/code', ['signup_ticket' => $ticket, 'binding' => $flow['binding']])
            ->assertNoContent();

        $code = null;
        Mail::assertQueued(EmailConfirmationCodeEmail::class, function (EmailConfirmationCodeEmail $mail) use (&$code) {
            $code = $mail->content()->with['code'];

            return $mail->hasTo('linus@example.com');
        });

        $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket, ['email_code' => $code]))
            ->assertOk()
            ->assertCookie('token');
        $this->assertNotNull(User::where('email', 'linus@example.com')->value('email_verified_at'));
    }

    public function test_too_many_wrong_codes_end_the_signup(): void
    {
        $this->fakeOidcProvider(trustEmail: false);
        [$flow, $ticket] = $this->signupTicket('linus@example.com', emailVerified: false);
        $this->postJson('/auth/sso/signup/code', ['signup_ticket' => $ticket, 'binding' => $flow['binding']]);

        for ($attempt = 1; $attempt < 5; $attempt++) {
            $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket, ['email_code' => '000000']))
                ->assertJsonPath('error_code', 'SSO_EMAIL_CODE_INVALID');
        }

        $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket, ['email_code' => '000000']))
            ->assertJsonPath('error_code', 'SSO_TICKET_INVALID');
    }

    public function test_asking_for_a_new_code_does_not_reset_the_wrong_code_limit(): void
    {
        $this->fakeOidcProvider(trustEmail: false);
        [$flow, $ticket] = $this->signupTicket('linus@example.com', emailVerified: false);

        for ($attempt = 1; $attempt < 5; $attempt++) {
            $this->postJson('/auth/sso/signup/code', ['signup_ticket' => $ticket, 'binding' => $flow['binding']])->assertNoContent();
            $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket, ['email_code' => '000000']))
                ->assertJsonPath('error_code', 'SSO_EMAIL_CODE_INVALID');
        }

        $this->postJson('/auth/sso/signup/code', ['signup_ticket' => $ticket, 'binding' => $flow['binding']])->assertNoContent();
        $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket, ['email_code' => '000000']))
            ->assertJsonPath('error_code', 'SSO_TICKET_INVALID');
    }

    public function test_a_trusted_email_never_needs_a_code(): void
    {
        [$flow, $ticket] = $this->signupTicket('grace@example.com');

        $this->postJson('/auth/sso/signup/code', ['signup_ticket' => $ticket, 'binding' => $flow['binding']])
            ->assertUnauthorized();
        Mail::assertNothingQueued();
    }

    public function test_names_are_validated_like_password_signups(): void
    {
        [$flow, $ticket] = $this->signupTicket('grace@example.com');

        $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket, ['first_name' => 'Visit https://spam.example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_name');
    }

    public function test_the_identity_signs_in_after_signup(): void
    {
        [$flow, $ticket] = $this->signupTicket('grace@example.com');
        $this->postJson('/auth/sso/signup', $this->signupPayload($flow, $ticket))->assertOk();

        $this->exchangeOidcCode($this->startOidcFlow(), [
            'sub' => 'new-subject',
            'email' => 'grace@example.com',
            'email_verified' => true,
        ])->assertOk()->assertJsonPath('data.status', 'AUTHENTICATED');

        $this->assertSame(1, DB::table('user_identities')->where('subject', 'new-subject')->count());
    }

    /**
     * @return array{0: array, 1: string}
     */
    private function signupTicket(string $email, bool $emailVerified = true): array
    {
        $flow = $this->startOidcFlow(['intent' => 'SIGNUP']);

        $response = $this->exchangeOidcCode($flow, [
            'sub' => 'new-subject',
            'email' => $email,
            'email_verified' => $emailVerified,
            'name' => 'Grace Hopper',
        ])->assertOk()->assertJsonPath('data.status', 'SIGNUP_REQUIRED');

        return [$flow, $response->json('data.signup.ticket')];
    }

    private function signupPayload(array $flow, string $ticket, array $overrides = []): array
    {
        return [
            'signup_ticket' => $ticket,
            'binding' => $flow['binding'],
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'timezone' => 'Europe/Dublin',
            'currency_code' => 'EUR',
            'locale' => 'en',
            'marketing_opt_in' => false,
            ...$overrides,
        ];
    }
}
