<?php

namespace Tests\Feature\Auth\Sso;

use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesSsoConnections;
use Tests\TestCase;

class SsoDomainCaptureTest extends TestCase
{
    use CreatesSsoConnections;
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
        config()->set('app.disable_registration', false);
        config()->set('app.saas_mode_enabled', true);
        $this->fakeOidcProvider();
    }

    public function test_password_signup_on_a_domain_that_requires_sso_is_sent_to_sso(): void
    {
        $this->accountRequiringSso();

        $this->postJson('/auth/register', $this->registration('Grace@ACME.test'))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED')
            ->assertJsonPath('context.email', 'Grace@ACME.test');

        $this->assertDatabaseMissing('users', ['email' => 'grace@acme.test']);
    }

    public function test_password_signup_stays_open_when_the_account_does_not_require_sso(): void
    {
        $this->accountRequiringSso(enforce: false);

        $this->postJson('/auth/register', $this->registration('grace@acme.test'))->assertCreated();
    }

    public function test_password_signup_stays_open_when_the_domain_is_suspended(): void
    {
        $this->accountRequiringSso(failingSince: now()->subDays(8)->toDateTimeString());

        $this->postJson('/auth/register', $this->registration('grace@acme.test'))->assertCreated();
    }

    public function test_social_signup_on_a_domain_that_requires_sso_is_refused_before_a_ticket_is_issued(): void
    {
        $this->accountRequiringSso();
        $flow = $this->startOidcFlow(['intent' => 'SIGNUP']);

        $this->exchangeOidcCode($flow, [
            'sub' => 'new-subject',
            'email' => 'grace@acme.test',
            'email_verified' => true,
            'name' => 'Grace Hopper',
        ])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED')
            ->assertJsonPath('context.email', 'grace@acme.test');

        $this->assertDatabaseMissing('users', ['email' => 'grace@acme.test']);
    }

    public function test_a_social_signup_ticket_issued_before_sso_was_required_cannot_create_the_account(): void
    {
        $connection = $this->accountRequiringSso(enforce: false);
        $flow = $this->startOidcFlow(['intent' => 'SIGNUP']);
        $ticket = $this->exchangeOidcCode($flow, [
            'sub' => 'new-subject',
            'email' => 'grace@acme.test',
            'email_verified' => true,
            'name' => 'Grace Hopper',
        ])->assertOk()->json('data.signup.ticket');

        $connection->update(['enforce_sso' => true]);

        $this->postJson('/auth/sso/signup', [
            'signup_ticket' => $ticket,
            'binding' => $flow['binding'],
            'first_name' => 'Grace',
            'timezone' => 'Europe/Dublin',
            'currency_code' => 'EUR',
            'locale' => 'en',
        ])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'SSO_REQUIRED');

        $this->assertDatabaseMissing('users', ['email' => 'grace@acme.test']);
    }

    private function accountRequiringSso(bool $enforce = true, ?string $failingSince = null): AccountSsoConnection
    {
        $accountId = User::factory()->withAccount()->create()->accounts()->first()->id;
        $this->addDomain($accountId, 'acme.test', failingSince: $failingSince);

        return $this->createConnection($accountId, ['enforce_sso' => $enforce]);
    }

    private function registration(string $email): array
    {
        return [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => $email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'timezone' => 'Europe/Dublin',
            'currency_code' => 'EUR',
            'locale' => 'en',
        ];
    }
}
