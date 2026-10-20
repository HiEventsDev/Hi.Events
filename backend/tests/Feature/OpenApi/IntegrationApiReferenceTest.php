<?php

namespace Tests\Feature\OpenApi;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Process;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class IntegrationApiReferenceTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;
    use UsesApiKeys;

    public function test_the_committed_reference_matches_the_code(): void
    {
        $result = Process::path(base_path())
            ->timeout(180)
            ->run([PHP_BINARY, '-d', 'memory_limit=1G', 'artisan', 'api-reference:export', '--check']);

        $this->assertTrue($result->successful(), trim($result->output().$result->errorOutput()));
    }

    public function test_signed_in_users_get_the_reference_for_api_key_endpoints_only(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();

        $response = $this->getJson('/api-reference', $this->headersFor($owner, $accountId))->assertOk();

        $paths = $response->json('paths');
        $this->assertArrayHasKey('/events', $paths);
        $this->assertArrayHasKey('/users/me', $paths);
        $this->assertSame(['get'], array_keys($paths['/users/me']));
        $this->assertArrayNotHasKey('/api-keys', $paths);
        $this->assertArrayNotHasKey('/users', $paths);
        $this->assertSame([], array_values(array_filter(array_keys($paths), static fn (string $path) => str_starts_with($path, '/auth/'))));
        $this->assertArrayHasKey('/public/events/{eventId}', $paths);
        $this->assertArrayNotHasKey('/public/instance', $paths);
        $this->assertArrayNotHasKey('/public/compliance', $paths);
        $this->assertArrayNotHasKey('/public/webhooks/stripe', $paths);

        $schemas = $response->json('components.schemas');
        $this->assertArrayNotHasKey('LoginRequest', $schemas);
        $this->assertArrayNotHasKey('CreatedApiKeyResource', $schemas);
        $this->assertArrayNotHasKey('UpsertAccountSsoConnectionRequest', $schemas);
        $this->assertArrayHasKey('EventResource', $schemas);
        $this->assertArrayNotHasKey('servers', $response->json());
        $this->assertSame(trim(file_get_contents(base_path('VERSION'))), $response->json('info.version'));
    }

    public function test_the_reference_needs_a_signed_in_session(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $apiKey = $this->createApiKey($owner, $accountId, ApiKeyAccess::READ_ONLY);

        $this->getJson('/api-reference')->assertUnauthorized();
        $this->getJson('/api-reference', $this->apiKeyHeaders($apiKey))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'API_KEY_NOT_ALLOWED');
    }
}
