<?php

namespace Tests\Feature\Console;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\FakesOidcProvider;
use Tests\TestCase;

class SsoStatusCommandTest extends TestCase
{
    use DatabaseTransactions;
    use FakesOidcProvider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sso.providers.google.client_id', null);
        config()->set('sso.providers.microsoft.client_id', null);
        config()->set('sso.password_login_disabled', true);
        User::factory()->create();
    }

    public function test_it_warns_that_users_are_locked_out_when_the_oidc_provider_does_not_trust_emails(): void
    {
        $this->fakeOidcProvider(trustEmail: false);

        $this->artisan('sso:status')
            ->expectsOutputToContain('Example SSO: none, because SSO_OIDC_TRUST_EMAIL is off.')
            ->expectsOutputToContain('Example SSO can\'t match these users to their accounts because SSO_OIDC_TRUST_EMAIL is off, so they can\'t sign in with it.')
            ->doesntExpectOutputToContain('once their provider email is verified')
            ->assertSuccessful();
    }

    public function test_it_explains_which_emails_a_trusted_oidc_provider_matches(): void
    {
        $this->fakeOidcProvider(trustEmail: true);

        $this->artisan('sso:status')
            ->expectsOutputToContain('Example SSO: emails it marks as verified, because SSO_OIDC_TRUST_EMAIL is on.')
            ->doesntExpectOutputToContain('SSO_OIDC_TRUST_EMAIL is off')
            ->assertSuccessful();
    }

    public function test_it_stays_quiet_about_matching_while_password_sign_in_is_on(): void
    {
        config()->set('sso.password_login_disabled', false);
        $this->fakeOidcProvider(trustEmail: false);

        $this->artisan('sso:status')
            ->doesntExpectOutputToContain('SSO_OIDC_TRUST_EMAIL')
            ->assertSuccessful();
    }
}
