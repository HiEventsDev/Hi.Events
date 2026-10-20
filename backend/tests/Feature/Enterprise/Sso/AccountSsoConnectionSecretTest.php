<?php

namespace Tests\Feature\Enterprise\Sso;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Models\User;
use HiEvents\Models\UserIdentity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CreatesSsoConnections;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\TestCase;

class AccountSsoConnectionSecretTest extends TestCase
{
    use ActsAsAccountUsers;
    use CreatesSsoConnections;
    use DatabaseTransactions;
    use ManagesFeatureFlags;

    private const ROGUE_ISSUER = 'https://rogue.okta.test';

    private User $owner;

    private int $accountId;

    private AccountSsoConnection $connection;

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
        $this->fakeConnectionIssuer(self::ROGUE_ISSUER);
        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->connection = $this->createConnection($this->accountId);
    }

    public function test_pointing_the_connection_at_another_issuer_needs_the_client_secret_again(): void
    {
        $member = $this->addAccountUser($this->accountId);
        UserIdentity::create([
            'user_id' => $member->id,
            'account_sso_connection_id' => $this->connection->id,
            'provider_key' => 'connection-'.$this->connection->id,
            'issuer' => self::CONNECTION_ISSUER,
            'subject' => 'okta-member',
        ]);

        $this->putJson($this->url(), $this->payload(['issuer' => self::ROGUE_ISSUER]), $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_secret');

        $this->assertConnectionUnchanged();
        $this->assertNull($member->fresh()->tokens_valid_after);
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), self::ROGUE_ISSUER));

        $this->putJson($this->url(), $this->payload(['issuer' => self::ROGUE_ISSUER, 'client_secret' => 'rogue-secret']), $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.issuer', self::ROGUE_ISSUER)
            ->assertJsonPath('data.status', 'INACTIVE');
        $this->assertSame('rogue-secret', $this->connection->fresh()->client_secret);
    }

    public function test_switching_the_provider_type_needs_the_client_secret_again(): void
    {
        $this->putJson($this->url(), $this->payload(['type' => 'OIDC', 'issuer' => self::ROGUE_ISSUER]), $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_secret');

        $this->assertConnectionUnchanged();
    }

    public function test_changing_the_client_id_needs_the_client_secret_again(): void
    {
        $this->putJson($this->url(), $this->payload(['client_id' => 'new-client']), $this->ownerHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_secret');

        $this->assertConnectionUnchanged();

        $this->putJson($this->url(), $this->payload(['client_id' => 'new-client', 'client_secret' => 'new-secret']), $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.client_id', 'new-client')
            ->assertJsonPath('data.status', 'INACTIVE');
        $this->assertSame('new-secret', $this->connection->fresh()->client_secret);
    }

    public function test_a_settings_change_keeps_the_saved_secret(): void
    {
        $this->putJson($this->url(), $this->payload(['session_ttl_minutes' => 120]), $this->ownerHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.session_ttl_minutes', 120);

        $this->assertSame('connection-secret', $this->connection->fresh()->client_secret);
    }

    private function assertConnectionUnchanged(): void
    {
        $connection = $this->connection->fresh();

        $this->assertSame('OKTA', $connection->type);
        $this->assertSame(self::CONNECTION_ISSUER, $connection->issuer);
        $this->assertSame(self::CONNECTION_CLIENT_ID, $connection->client_id);
        $this->assertSame('connection-secret', $connection->client_secret);
        $this->assertSame('ACTIVE', $connection->status);
        $this->assertNotNull($connection->last_tested_at);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'OKTA',
            'issuer' => self::CONNECTION_ISSUER,
            'client_id' => self::CONNECTION_CLIENT_ID,
            'client_secret' => null,
            'session_ttl_minutes' => 720,
            'jit_provisioning_enabled' => false,
            'jit_default_role' => 'ORGANIZER',
            ...$overrides,
        ];
    }

    private function url(): string
    {
        return "/accounts/$this->accountId/sso/connection";
    }

    private function ownerHeaders(): array
    {
        return $this->bearer($this->tokenFor($this->owner, $this->accountId));
    }
}
