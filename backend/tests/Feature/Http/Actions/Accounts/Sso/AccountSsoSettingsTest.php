<?php

namespace Tests\Feature\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\Sso\Jobs\RecheckSsoDomainsJob;
use HiEvents\Enterprise\Sso\Mail\SsoSettingsChangedMail;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Models\User;
use HiEvents\Models\UserIdentity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CreatesSsoConnections;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Concerns\ManagesLicence;
use Tests\TestCase;

class AccountSsoSettingsTest extends TestCase
{
    use ActsAsAccountUsers;
    use CreatesSsoConnections;
    use DatabaseTransactions;
    use ManagesFeatureFlags;
    use ManagesLicence;

    private User $owner;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setFeatureFlagDefault(FeatureFlag::ENTERPRISE_SSO, true);

        Mail::fake();
        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);
        $this->fakeConnectionIssuer();
        [$this->owner, $this->accountId] = $this->createAccountOwner();
    }

    public function test_setting_up_a_connection_needs_a_licence(): void
    {
        $this->useLicenceKey(null);

        $this->getJson($this->url(), $this->ownerHeaders())->assertOk()->assertJsonPath('data.connection', null);
        $this->postJson($this->url('/connection'), $this->oktaPayload(), $this->ownerHeaders())->assertForbidden();
        $this->postJson($this->url('/domains'), ['domain' => 'acme.test'], $this->ownerHeaders())->assertForbidden();
        $this->assertDatabaseMissing('account_sso_connections', ['account_id' => $this->accountId]);
    }

    public function test_an_unlicensed_account_can_still_switch_single_sign_on_off(): void
    {
        $this->createConnection($this->accountId, ['enforce_sso' => true]);
        $this->useLicenceKey(null);

        $this->putJson($this->url('/enforcement'), ['enabled' => true], $this->ownerHeaders())
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');
        $this->putJson($this->url('/enforcement'), ['enabled' => false], $this->ownerHeaders())->assertOk();
        $this->putJson($this->url('/connection/status'), ['status' => 'INACTIVE'], $this->ownerHeaders())->assertOk();
        $this->putJson($this->url('/connection/status'), ['status' => 'ACTIVE'], $this->ownerHeaders())
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');
        $this->deleteJson($this->url('/connection'), [], $this->ownerHeaders())->assertNoContent();
    }

    public function test_hi_events_cloud_rolls_configuration_out_by_feature_flag(): void
    {
        $this->useCloudLicence();
        $this->setFeatureFlagDefault(FeatureFlag::ENTERPRISE_SSO, false);

        $this->getJson($this->url(), $this->ownerHeaders())->assertOk();
        $this->postJson($this->url('/connection'), $this->oktaPayload(), $this->ownerHeaders())
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');

        $this->setFeatureFlagDefault(FeatureFlag::ENTERPRISE_SSO, true);
        $this->postJson($this->url('/connection'), $this->oktaPayload(), $this->ownerHeaders())->assertCreated();
    }

    public function test_only_members_who_can_manage_the_account_see_or_change_it(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $headers = $this->bearer($this->tokenFor($organizer, $this->accountId));

        $this->getJson($this->url(), $headers)->assertForbidden();
        $this->postJson($this->url('/connection'), $this->oktaPayload(), $this->bearer($this->tokenFor($organizer, $this->accountId)))->assertForbidden();
    }

    public function test_another_accounts_connection_is_out_of_reach(): void
    {
        [$other, $otherAccountId] = $this->createAccountOwner();
        $this->createConnection($otherAccountId);

        $this->getJson("/accounts/$otherAccountId/sso", $this->ownerHeaders())->assertForbidden();
    }

    public function test_a_connection_is_created_after_discovery_and_its_secret_is_never_returned(): void
    {
        $response = $this->postJson($this->url('/connection'), $this->oktaPayload(), $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.type', 'OKTA')
            ->assertJsonPath('data.status', 'INACTIVE')
            ->assertJsonPath('data.issuer', self::CONNECTION_ISSUER);

        $this->assertStringNotContainsString('super-secret', $response->getContent());
        $this->assertArrayNotHasKey('client_secret', $response->json('data'));
        $stored = DB::table('account_sso_connections')->where('account_id', $this->accountId)->value('client_secret');
        $this->assertNotSame('super-secret', $stored);
        $this->assertSame('super-secret', AccountSsoConnection::where('account_id', $this->accountId)->first()->client_secret);

        $overview = $this->getJson($this->url(), $this->ownerHeaders())->assertOk();
        $this->assertStringNotContainsString('super-secret', $overview->getContent());
        $this->assertSame(rtrim(config('app.frontend_url'), '/').'/sso/callback', $overview->json('data.redirect_uri'));
    }

    public function test_an_issuer_on_a_private_network_or_over_http_is_refused(): void
    {
        $this->fakeDnsAddresses['internal.example.test'] = ['10.0.0.8'];

        $this->postJson($this->url('/connection'), [...$this->oktaPayload(), 'issuer' => 'https://internal.example.test'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issuer');
        $this->postJson($this->url('/connection'), [...$this->oktaPayload(), 'issuer' => 'http://acme.okta.test'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issuer');
        $this->postJson($this->url('/connection'), [...$this->oktaPayload(), 'issuer' => 'https://accounts.google.com'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issuer');

        $this->assertDatabaseMissing('account_sso_connections', ['account_id' => $this->accountId]);
    }

    public function test_the_issuer_check_reports_problems_inline(): void
    {
        $this->postJson($this->url('/connection/check'), $this->oktaPayload(), $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.valid', true);
        $this->postJson($this->url('/connection/check'), [...$this->oktaPayload(), 'issuer' => 'https://missing.example.test'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issuer');
        $this->postJson($this->url('/connection/check'), ['type' => 'ENTRA', 'client_id' => 'x', 'tenant_id' => 'not-a-guid'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tenant_id');
    }

    public function test_secrets_rotate_in_place_and_changing_the_provider_requires_a_new_test(): void
    {
        $this->createConnection($this->accountId);

        $this->putJson($this->url('/connection'), [...$this->oktaPayload(), 'client_secret' => null, 'session_ttl_minutes' => 120], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.session_ttl_minutes', 120);
        $this->assertSame('connection-secret', AccountSsoConnection::where('account_id', $this->accountId)->first()->client_secret);

        $connection = AccountSsoConnection::where('account_id', $this->accountId)->first();
        $connection->update(['enforce_sso' => true]);
        $this->putJson($this->url('/connection'), [...$this->oktaPayload(), 'client_secret' => 'rotated-secret'], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');
        $this->assertSame('rotated-secret', $connection->fresh()->client_secret);
        $this->putJson($this->url('/connection'), [...$this->oktaPayload(), 'client_id' => 'new-client', 'client_secret' => null], $this->ownerHeaders())
            ->assertUnprocessable();
        $connection->update(['enforce_sso' => false]);

        $this->putJson($this->url('/connection'), [...$this->oktaPayload(), 'client_id' => 'new-client', 'client_secret' => null], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_secret');
        $this->assertSame('rotated-secret', $connection->fresh()->client_secret);

        $this->putJson($this->url('/connection'), [...$this->oktaPayload(), 'client_id' => 'new-client', 'client_secret' => 'new-secret'], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'INACTIVE')
            ->assertJsonPath('data.last_tested_at', null);
    }

    public function test_a_test_sign_in_shows_what_the_provider_sends_and_unlocks_activation(): void
    {
        $this->createConnection($this->accountId, ['status' => 'INACTIVE', 'last_tested_at' => null]);
        $this->addDomain($this->accountId, 'acme.test');
        $token = $this->tokenFor($this->owner, $this->accountId);

        $this->putJson($this->url('/connection/status'), ['status' => 'ACTIVE'], $this->bearer($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $flow = $this->flowFrom($this->postJson($this->url('/connection/test'), ['return_path' => '/settings/account'], $this->bearer($token))
            ->assertOk()
            ->json('data'));
        $this->assertSame('login', $flow['query']['prompt']);

        $this->exchangeOidcCode(
            $flow,
            $this->connectionClaims('okta-tester', 'tester@acme.test', ['groups' => ['Event staff']]),
            '/users/me/sso-identities/exchange',
            $this->bearer($token),
        )->assertOk()
            ->assertJsonPath('data.status', 'TESTED')
            ->assertJsonPath('data.test.email', 'tester@acme.test')
            ->assertJsonPath('data.test.email_domain_verified', true)
            ->assertJsonPath('data.test.groups', ['Event staff']);

        $this->assertDatabaseMissing('users', ['email' => 'tester@acme.test']);
        $this->assertDatabaseMissing('user_identities', ['subject' => 'okta-tester']);

        $this->putJson($this->url('/connection/status'), ['status' => 'ACTIVE'], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_a_test_sign_in_cannot_be_finished_by_another_session(): void
    {
        $this->createConnection($this->accountId, ['status' => 'INACTIVE']);
        $flow = $this->flowFrom($this->postJson($this->url('/connection/test'), [], $this->ownerHeaders())->json('data'));

        $this->exchangeOidcCode($flow, $this->connectionClaims('okta-tester', 'tester@acme.test'), '/users/me/sso-identities/exchange', $this->ownerHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SSO_STATE_INVALID');
    }

    public function test_deactivating_ends_sessions_and_deleting_removes_the_connection_identities(): void
    {
        $connection = $this->createConnection($this->accountId);
        $member = $this->addAccountUser($this->accountId);
        UserIdentity::create([
            'user_id' => $member->id,
            'account_sso_connection_id' => $connection->id,
            'provider_key' => 'connection-'.$connection->id,
            'issuer' => self::CONNECTION_ISSUER,
            'subject' => 'okta-member',
        ]);

        $this->putJson($this->url('/connection/status'), ['status' => 'INACTIVE'], $this->ownerHeaders())->assertOk();
        $this->assertNotNull($member->fresh()->tokens_valid_after);

        $this->deleteJson($this->url('/connection'), [], $this->ownerHeaders())->assertNoContent();
        $this->assertDatabaseMissing('account_sso_connections', ['id' => $connection->id]);
        $this->assertDatabaseMissing('user_identities', ['subject' => 'okta-member']);
    }

    public function test_domains_are_verified_with_a_txt_record(): void
    {
        $domain = $this->postJson($this->url('/domains'), ['domain' => ' ACME.test. '], $this->ownerHeaders())
            ->assertCreated()
            ->assertJsonPath('data.domain', 'acme.test')
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.txt_record_name', '_hievents-verify.acme.test')
            ->json('data');

        $this->postJson($this->url("/domains/{$domain['id']}/verify"), [], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        $this->fakeTxtRecords['_hievents-verify.acme.test'] = ['v=spf1 -all', $domain['txt_record_value']];

        $this->postJson($this->url("/domains/{$domain['id']}/verify"), [], $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'VERIFIED');
    }

    public function test_free_mail_domains_and_duplicates_are_refused(): void
    {
        $this->postJson($this->url('/domains'), ['domain' => 'gmail.com'], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');
        $this->postJson($this->url('/domains'), ['domain' => 'not a domain'], $this->ownerHeaders())
            ->assertUnprocessable();

        $this->postJson($this->url('/domains'), ['domain' => 'acme.test'], $this->ownerHeaders())->assertCreated();
        $this->postJson($this->url('/domains'), ['domain' => 'acme.test'], $this->ownerHeaders())->assertUnprocessable();
    }

    public function test_a_domain_verified_by_another_account_goes_to_manual_review(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $this->addDomain($otherAccountId, 'acme.test');
        $domainId = $this->addDomain($this->accountId, 'acme.test', verified: false);
        $this->fakeTxtRecords['_hievents-verify.acme.test'] = ['hievents-verify=token-acme.test'];

        $this->postJson($this->url("/domains/$domainId/verify"), [], $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');

        $this->assertNull(DB::table('account_sso_domains')->where('id', $domainId)->value('verified_at'));
    }

    public function test_another_accounts_domain_cannot_be_verified_or_deleted(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $domainId = $this->addDomain($otherAccountId, 'other.test', verified: false);

        $this->postJson($this->url("/domains/$domainId/verify"), [], $this->ownerHeaders())->assertUnprocessable();
        $this->deleteJson($this->url("/domains/$domainId"), [], $this->ownerHeaders())->assertUnprocessable();
        $this->assertDatabaseHas('account_sso_domains', ['id' => $domainId]);
    }

    public function test_the_daily_check_marks_a_missing_record_as_failing_and_clears_it_when_it_returns(): void
    {
        $domainId = $this->addDomain($this->accountId, 'acme.test');

        app()->call([new RecheckSsoDomainsJob, 'handle']);
        $failingSince = DB::table('account_sso_domains')->where('id', $domainId)->value('failing_since');
        $this->assertNotNull($failingSince);

        $this->travel(2)->days();
        app()->call([new RecheckSsoDomainsJob, 'handle']);
        $this->assertSame($failingSince, DB::table('account_sso_domains')->where('id', $domainId)->value('failing_since'));

        $this->fakeTxtRecords['_hievents-verify.acme.test'] = ['hievents-verify=token-acme.test'];
        app()->call([new RecheckSsoDomainsJob, 'handle']);
        $this->assertNull(DB::table('account_sso_domains')->where('id', $domainId)->value('failing_since'));
    }

    public function test_the_owner_is_emailed_when_another_admin_changes_the_identity_provider(): void
    {
        $this->createConnection($this->accountId);
        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);

        $this->putJson($this->url('/connection'), [...$this->oktaPayload(), 'client_id' => 'rogue-client'], $this->bearer($this->tokenFor($admin, $this->accountId)))
            ->assertOk();
        $this->putJson($this->url('/connection'), [...$this->oktaPayload(), 'client_id' => 'rogue-client', 'session_ttl_minutes' => 60], $this->ownerHeaders())
            ->assertOk();

        Mail::assertQueued(SsoSettingsChangedMail::class, 1);
        Mail::assertQueued(SsoSettingsChangedMail::class, fn (SsoSettingsChangedMail $mail) => $mail->hasTo($this->owner->email));
    }

    public function test_the_owner_reads_the_change_in_their_own_language(): void
    {
        $this->createConnection($this->accountId);
        $this->owner->update(['locale' => 'de']);
        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);
        $expected = __('Changed the identity provider to :issuer', ['issuer' => 'https://acme.okta.test'], 'de');

        $this->putJson($this->url('/connection'), [...$this->oktaPayload(), 'client_id' => 'rogue-client'], $this->bearer($this->tokenFor($admin, $this->accountId)))
            ->assertOk();

        $this->assertNotSame('Changed the identity provider to https://acme.okta.test', $expected);
        Mail::assertQueued(SsoSettingsChangedMail::class, fn (SsoSettingsChangedMail $mail) => $mail->content()->with['change'] === $expected);
    }

    private function url(string $suffix = ''): string
    {
        return "/accounts/$this->accountId/sso$suffix";
    }

    private function ownerHeaders(): array
    {
        return $this->bearer($this->tokenFor($this->owner, $this->accountId));
    }

    private function oktaPayload(): array
    {
        return [
            'type' => 'OKTA',
            'issuer' => self::CONNECTION_ISSUER.'/',
            'client_id' => self::CONNECTION_CLIENT_ID,
            'client_secret' => 'super-secret',
            'session_ttl_minutes' => 720,
            'jit_provisioning_enabled' => true,
            'jit_default_role' => 'ORGANIZER',
        ];
    }
}
