<?php

namespace Tests\Feature\OAuth;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Mail\User\OAuthAppConnectedMail;
use HiEvents\Mcp\Servers\HiEventsServer;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyService;
use HiEvents\Services\Domain\Auth\OAuth\OAuthPruneService;
use HiEvents\Services\Domain\Auth\OAuth\OAuthTokenService;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use ReflectionClass;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CallsMcpTools;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class OAuthFlowTest extends TestCase
{
    use ActsAsAccountUsers;
    use CallsMcpTools;
    use DatabaseTransactions;
    use UsesApiKeys;

    private const PASSWORD = 'correct-horse-battery';

    private const REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';

    private const VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk-a-much-longer-verifier';

    private User $owner;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['app.frontend_url' => 'https://app.example.test']);
        URL::forceScheme('https');
        URL::forceRootUrl('https://api.example.test');

        $this->owner = User::factory()->password(self::PASSWORD)->withAccount()->create();
        $this->accountId = $this->owner->accounts()->first()->id;
        $this->createOrganizer($this->accountId);
    }

    public function test_discovery_points_clients_at_the_authorization_server(): void
    {
        $this->postJson('/mcp', $this->rpc('tools/list'))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer resource_metadata="https://api.example.test/.well-known/oauth-protected-resource/mcp", scope="mcp:read mcp:write"');

        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', 'https://api.example.test/mcp')
            ->assertJsonPath('authorization_servers.0', 'https://api.example.test');

        foreach (['/.well-known/oauth-authorization-server', '/.well-known/oauth-authorization-server/api', '/.well-known/openid-configuration'] as $path) {
            $this->getJson($path)
                ->assertOk()
                ->assertJsonPath('issuer', 'https://api.example.test')
                ->assertJsonPath('token_endpoint', 'https://api.example.test/oauth/token')
                ->assertJsonPath('code_challenge_methods_supported', ['S256']);
        }
    }

    public function test_a_proxy_that_strips_a_path_prefix_can_restore_it_in_every_url(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server', ['X-Forwarded-Prefix' => '/api'])
            ->assertOk()
            ->assertJsonPath('issuer', 'https://api.example.test/api')
            ->assertJsonPath('token_endpoint', 'https://api.example.test/api/oauth/token');

        $this->getJson('/.well-known/oauth-protected-resource/mcp', ['X-Forwarded-Prefix' => '/api'])
            ->assertJsonPath('resource', 'https://api.example.test/api/mcp');
    }

    public function test_registration_accepts_https_loopback_and_app_schemes_only(): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI]])
            ->assertCreated()
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonPath('client_name', 'Claude');

        $this->postJson('/oauth/register', ['client_name' => 'Claude Code', 'redirect_uris' => ['http://localhost:54321/callback']])->assertCreated();
        $this->postJson('/oauth/register', ['client_name' => 'Cursor', 'redirect_uris' => ['cursor://anysphere.cursor-retrieval/oauth/callback']])->assertCreated();

        foreach (['http://evil.example/callback', 'javascript:alert(1)', 'https://app.example/cb#fragment'] as $uri) {
            $this->postJson('/oauth/register', ['redirect_uris' => [$uri]])
                ->assertStatus(400)
                ->assertJsonPath('error', 'invalid_redirect_uri');
        }

        $this->postJson('/oauth/register', ['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'client_secret_basic'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_client_metadata');
    }

    public function test_client_names_are_cleaned_before_they_reach_screens_and_email(): void
    {
        $this->postJson('/oauth/register', ['client_name' => '<script>Evil</script> App', 'redirect_uris' => [self::REDIRECT_URI]])
            ->assertCreated()
            ->assertJsonPath('client_name', 'scriptEvil/script App');

        $this->postJson('/oauth/register', ['client_name' => '<<>>', 'redirect_uris' => [self::REDIRECT_URI]])
            ->assertJsonPath('client_name', 'Unnamed app');
    }

    public function test_authorize_never_redirects_to_an_unregistered_uri(): void
    {
        $clientId = $this->registerClient();

        $this->get('/oauth/authorize?'.http_build_query(['client_id' => 'nope', 'redirect_uri' => self::REDIRECT_URI]))
            ->assertRedirect('https://app.example.test/connect?error=invalid_client');

        $this->get('/oauth/authorize?'.http_build_query(['client_id' => $clientId, 'redirect_uri' => 'https://attacker.example/cb']))
            ->assertRedirect('https://app.example.test/connect?error=invalid_redirect_uri');
    }

    public function test_authorize_reports_request_errors_back_to_the_client(): void
    {
        $clientId = $this->registerClient();

        $location = $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'state' => 'xyz',
        ]))->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith(self::REDIRECT_URI.'?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(['error' => 'invalid_request', 'state' => 'xyz', 'iss' => 'https://api.example.test'], $query);
    }

    public function test_full_flow_connects_an_app_that_can_call_the_mcp_server(): void
    {
        $clientId = $this->registerClient();
        $requestId = $this->startAuthorization($clientId);

        $this->getJson("/oauth/authorization-requests/$requestId", $this->headersFor($this->owner, $this->accountId))
            ->assertOk()
            ->assertJsonPath('data.client_name', 'Claude')
            ->assertJsonPath('data.redirect_host', 'claude.ai')
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.requested_access', 'READ_WRITE');

        $code = $this->approve($requestId);
        $tokens = $this->exchange($clientId, $code)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', OAuthTokenService::ACCESS_TOKEN_TTL_SECONDS)
            ->assertJsonPath('scope', 'mcp:read mcp:write')
            ->json();

        $this->mcp($tokens['access_token'], 'tools/list')->assertOk()->assertJsonCount(count((new ReflectionClass(HiEventsServer::class))->getDefaultProperties()['tools']), 'result.tools');
        $this->callTool($tokens['access_token'], 'list_organizers')->assertJsonPath('result.isError', false);

        Mail::assertQueued(OAuthAppConnectedMail::class, fn (OAuthAppConnectedMail $mail) => $mail->hasTo($this->owner->email));
        $this->assertDatabaseHas('oauth_connections', ['user_id' => $this->owner->id, 'account_id' => $this->accountId, 'access' => 'READ_WRITE']);
    }

    public function test_the_code_is_bound_to_the_pkce_verifier_and_redirect_uri(): void
    {
        $clientId = $this->registerClient();

        $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)), verifier: str_repeat('x', 50))
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');

        $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)), redirectUri: 'https://claude.ai/other')
            ->assertJsonPath('error', 'invalid_grant');

        $this->assertDatabaseCount('oauth_connections', 0);
    }

    public function test_replaying_a_code_revokes_the_connection_it_created(): void
    {
        $clientId = $this->registerClient();
        $code = $this->approve($this->startAuthorization($clientId));

        $this->exchange($clientId, $code)->assertOk();
        $this->exchange($clientId, $code)->assertJsonPath('error', 'invalid_grant');

        $this->assertDatabaseCount('oauth_connections', 0);
    }

    public function test_refresh_rotates_and_reuse_of_an_old_refresh_token_revokes_everything(): void
    {
        $clientId = $this->registerClient();
        $first = $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)))->json();

        $second = $this->refresh($clientId, $first['refresh_token'])->assertOk()->json();
        $this->assertNotSame($first['refresh_token'], $second['refresh_token']);
        $this->refresh($clientId, $second['refresh_token'])->assertOk();

        $this->travel(OAuthTokenService::REFRESH_REUSE_GRACE_SECONDS + 5)->seconds();

        $this->refresh($clientId, $first['refresh_token'])->assertJsonPath('error', 'invalid_grant');
        $this->assertDatabaseCount('oauth_connections', 0);
        $this->mcp($second['access_token'], 'tools/list')->assertUnauthorized();
    }

    public function test_a_read_only_connection_cannot_write(): void
    {
        $clientId = $this->registerClient();
        $tokens = $this->exchange($clientId, $this->approve($this->startAuthorization($clientId), access: 'READ_ONLY'))
            ->assertJsonPath('scope', 'mcp:read')
            ->json();

        $this->callTool($tokens['access_token'], 'list_organizers')->assertJsonPath('result.isError', false);
        $tools = collect($this->mcp($tokens['access_token'], 'tools/list')->assertOk()->json('result.tools'));
        $this->assertTrue($tools->every(fn (array $tool) => $tool['annotations']['readOnlyHint'] ?? false));
        $this->callTool($tokens['access_token'], 'create_organizer', [
            'name' => 'Nope', 'email' => 'nope@example.com', 'timezone' => 'UTC', 'currency' => 'EUR',
        ])->assertJsonStructure(['error' => ['code', 'message']]);
        $this->assertDatabaseMissing('organizers', ['name' => 'Nope']);
    }

    public function test_approval_requires_identity_confirmation_and_a_verified_email(): void
    {
        $clientId = $this->registerClient();
        $requestId = $this->startAuthorization($clientId);

        $this->postJson("/oauth/authorization-requests/$requestId/approve", ['access' => 'READ_WRITE', 'password' => 'wrong'], $this->headersFor($this->owner, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->owner->forceFill(['email_verified_at' => null])->save();

        $this->postJson("/oauth/authorization-requests/$requestId/approve", ['access' => 'READ_WRITE', 'password' => self::PASSWORD], $this->headersFor($this->owner, $this->accountId))
            ->assertConflict();
    }

    public function test_a_viewer_can_only_grant_read_access(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $viewer->forceFill(['password' => bcrypt(self::PASSWORD)])->save();
        $requestId = $this->startAuthorization($this->registerClient());

        $this->postJson("/oauth/authorization-requests/$requestId/approve", ['access' => 'READ_WRITE', 'password' => self::PASSWORD], $this->headersFor($viewer, $this->accountId))
            ->assertForbidden();

        $this->postJson("/oauth/authorization-requests/$requestId/approve", ['access' => 'READ_ONLY', 'password' => self::PASSWORD], $this->headersFor($viewer, $this->accountId))
            ->assertOk();
    }

    public function test_denying_sends_the_client_access_denied(): void
    {
        $requestId = $this->startAuthorization($this->registerClient(), state: 'abc');

        $url = $this->postJson("/oauth/authorization-requests/$requestId/deny", [], $this->headersFor($this->owner, $this->accountId))
            ->assertOk()
            ->json('data.redirect_url');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('access_denied', $query['error']);
        $this->assertSame('abc', $query['state']);

        $this->getJson("/oauth/authorization-requests/$requestId", $this->headersFor($this->owner, $this->accountId))->assertNotFound();
    }

    public function test_credential_resets_and_deactivation_kill_connections(): void
    {
        $clientId = $this->registerClient();
        $tokens = $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)))->json();

        app(UserSessionRevocationService::class)->revokeAll($this->owner->id);

        $this->assertDatabaseCount('oauth_connections', 0);
        $this->refresh($clientId, $tokens['refresh_token'])->assertJsonPath('error', 'invalid_grant');

        $tokens = $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)))->json();
        app(ApiKeyService::class)->revokeAllForMember($this->owner->id, $this->accountId);

        $this->assertDatabaseCount('oauth_connections', 0);
        $this->mcp($tokens['access_token'], 'tools/list')->assertUnauthorized();
    }

    public function test_refresh_stops_once_the_member_is_no_longer_active(): void
    {
        $clientId = $this->registerClient();
        $tokens = $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)))->json();

        DB::table('account_users')->where(['user_id' => $this->owner->id, 'account_id' => $this->accountId])->update(['status' => UserStatus::INACTIVE->name]);

        $this->refresh($clientId, $tokens['refresh_token'])->assertJsonPath('error', 'invalid_grant');
        $this->assertDatabaseCount('oauth_connections', 0);
    }

    public function test_connected_apps_are_listed_apart_from_api_keys_and_can_be_disconnected(): void
    {
        $clientId = $this->registerClient();
        $tokens = $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)))->json();
        $this->createApiKey($this->owner, $this->accountId);
        $headers = $this->headersFor($this->owner, $this->accountId);

        $connectionId = $this->getJson('/oauth/connections', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.app_name', 'Claude')
            ->assertJsonPath('data.0.redirect_host', 'claude.ai')
            ->json('data.0.id');

        $this->getJson('/api-keys', $this->headersFor($this->owner, $this->accountId))->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/oauth/connections/$connectionId", [], $this->headersFor($this->owner, $this->accountId))->assertNoContent();
        $this->mcp($tokens['access_token'], 'tools/list')->assertUnauthorized();
    }

    public function test_the_revocation_endpoint_disconnects_the_app(): void
    {
        $clientId = $this->registerClient();
        $tokens = $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)))->json();

        $this->post('/oauth/revoke', ['token' => $tokens['refresh_token'], 'client_id' => $clientId])->assertOk();
        $this->post('/oauth/revoke', ['token' => 'unknown'])->assertOk();

        $this->assertDatabaseCount('oauth_connections', 0);
    }

    public function test_idle_dynamic_clients_and_dead_connections_are_pruned(): void
    {
        $abandoned = $this->registerClient();
        $disconnected = $this->registerClient();
        $clientId = $this->registerClient();
        $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)));
        $idleFor = now()->subDays(OAuthPruneService::IDLE_CLIENT_DAYS + 1);
        DB::table('oauth_clients')->update(['created_at' => $idleFor, 'last_used_at' => null]);
        DB::table('oauth_clients')->where('client_id', $disconnected)->update(['last_used_at' => now()->subDays(30)]);

        app(OAuthPruneService::class)->prune();

        $this->assertDatabaseMissing('oauth_clients', ['client_id' => $abandoned]);
        $this->assertDatabaseHas('oauth_clients', ['client_id' => $disconnected]);
        $this->assertDatabaseHas('oauth_clients', ['client_id' => $clientId]);

        DB::table('oauth_refresh_tokens')->update(['expires_at' => now()->subDay()]);
        app(OAuthPruneService::class)->prune();

        $this->assertDatabaseCount('oauth_connections', 0);
    }

    public function test_reconnecting_the_same_client_replaces_its_old_connection(): void
    {
        $clientId = $this->registerClient();
        $first = $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)))->assertOk()->json();
        $second = $this->exchange($clientId, $this->approve($this->startAuthorization($clientId)))->assertOk()->json();

        $this->assertDatabaseCount('oauth_connections', 1);
        $this->mcp($first['access_token'], 'tools/list')->assertUnauthorized();
        $this->refresh($clientId, $first['refresh_token'])->assertJsonPath('error', 'invalid_grant');
        $this->mcp($second['access_token'], 'tools/list')->assertOk();
    }

    public function test_an_authorization_request_can_only_be_approved_once(): void
    {
        $requestId = $this->startAuthorization($this->registerClient());
        $this->approve($requestId);

        $this->postJson(
            "/oauth/authorization-requests/$requestId/approve",
            ['access' => 'READ_WRITE', 'password' => self::PASSWORD],
            $this->headersFor($this->owner, $this->accountId),
        )->assertNotFound();
    }

    public function test_malformed_authorization_request_ids_never_reach_the_handler(): void
    {
        $this->getJson('/oauth/authorization-requests/short', $this->headersFor($this->owner, $this->accountId))->assertNotFound();
    }

    public function test_the_connection_cap_is_enforced_when_the_code_is_exchanged(): void
    {
        $clientId = $this->registerClient();
        $code = $this->approve($this->startAuthorization($clientId));

        for ($i = 0; $i < OAuthTokenService::MAX_CONNECTIONS_PER_MEMBER; $i++) {
            $otherClientId = DB::table('oauth_clients')->insertGetId([
                'client_id' => "hiec_other_$i",
                'name' => 'Other',
                'redirect_uris' => json_encode([self::REDIRECT_URI]),
                'registration_type' => 'DYNAMIC',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('oauth_connections')->insert([
                'oauth_client_id' => $otherClientId,
                'user_id' => $this->owner->id,
                'account_id' => $this->accountId,
                'access' => 'READ_ONLY',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->exchange($clientId, $code)->assertStatus(400)->assertJsonPath('error', 'access_denied');
        $this->assertDatabaseCount('oauth_connections', OAuthTokenService::MAX_CONNECTIONS_PER_MEMBER);
    }

    private function registerClient(): string
    {
        return $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI]])
            ->assertCreated()
            ->json('client_id');
    }

    private function startAuthorization(string $clientId, string $state = 'state-123'): string
    {
        $location = $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', self::VERIFIER, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'state' => $state,
            'scope' => 'mcp:read mcp:write',
            'resource' => 'https://api.example.test/mcp',
        ]))->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://app.example.test/connect?request=', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        return $query['request'];
    }

    private function approve(string $requestId, string $access = 'READ_WRITE'): string
    {
        $url = $this->postJson(
            "/oauth/authorization-requests/$requestId/approve",
            ['access' => $access, 'password' => self::PASSWORD],
            $this->headersFor($this->owner, $this->accountId),
        )->assertOk()->json('data.redirect_url');

        $this->assertStringStartsWith(self::REDIRECT_URI.'?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('state-123', $query['state']);
        $this->assertSame('https://api.example.test', $query['iss']);

        return $query['code'];
    }

    private function exchange(string $clientId, string $code, ?string $verifier = null, ?string $redirectUri = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => $redirectUri ?? self::REDIRECT_URI,
            'code_verifier' => $verifier ?? self::VERIFIER,
            'resource' => 'https://api.example.test/mcp',
        ]);
    }

    private function refresh(string $clientId, string $refreshToken): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'refresh_token' => $refreshToken,
        ]);
    }
}
