<?php

namespace Tests\Unit\Services\Domain\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoProviderType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\Network\HostResolver;
use HiEvents\Services\Domain\Auth\Sso\Network\SsrfGuard;
use HiEvents\Services\Domain\Auth\Sso\OidcDiscoveryClient;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OidcDiscoveryClientTest extends TestCase
{
    private const ISSUER = 'https://idp.example.test';

    private Factory $http;

    private OidcDiscoveryClient $client;

    private HostResolver|MockInterface $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new Factory;
        $this->resolver = Mockery::mock(HostResolver::class);
        $this->resolver->shouldReceive('resolve')->andReturn(['93.184.216.34'])->byDefault();
        $this->client = new OidcDiscoveryClient($this->http, new Repository(new ArrayStore), new SsrfGuard($this->resolver));
    }

    public function test_discovery_is_cached(): void
    {
        $this->http->fake([self::ISSUER.'/.well-known/openid-configuration' => $this->http->response($this->document())]);

        $this->client->discover($this->provider());
        $this->client->discover($this->provider());

        $this->http->assertSentCount(1);
    }

    public function test_an_issuer_published_with_a_trailing_slash_is_accepted(): void
    {
        $this->http->fake(['*' => $this->http->response($this->document(['issuer' => self::ISSUER.'/']))]);

        $this->assertSame(self::ISSUER.'/', $this->client->discover($this->provider())['issuer']);
    }

    public function test_a_used_or_expired_code_reads_as_an_expired_link(): void
    {
        $this->http->fake(['*' => $this->http->response(['error' => 'invalid_grant'], 400)]);

        try {
            $this->client->post($this->provider(), self::ISSUER.'/token', ['code' => 'old'], null);
            $this->fail('Expected the token request to be rejected');
        } catch (SsoException $exception) {
            $this->assertSame(SsoErrorCode::STATE_INVALID, $exception->errorCode);
        }
    }

    public function test_a_rejected_client_is_not_reported_as_an_expired_link(): void
    {
        $this->http->fake(['*' => $this->http->response(['error' => 'invalid_client'], 401)]);

        try {
            $this->client->post($this->provider(), self::ISSUER.'/token', ['code' => 'fresh'], null);
            $this->fail('Expected the token request to be rejected');
        } catch (SsoException $exception) {
            $this->assertSame(SsoErrorCode::TOKEN_INVALID, $exception->errorCode);
            $this->assertStringContainsString('invalid_client', $exception->getMessage());
        }
    }

    public function test_a_document_for_another_issuer_is_rejected(): void
    {
        $this->http->fake(['*' => $this->http->response($this->document(['issuer' => 'https://attacker.example.test']))]);

        try {
            $this->client->discover($this->provider());
            $this->fail('Expected the discovery document to be rejected');
        } catch (SsoException $exception) {
            $this->assertSame(SsoErrorCode::PROVIDER_UNAVAILABLE, $exception->errorCode);
        }
    }

    public function test_plain_http_endpoints_are_rejected_for_an_https_issuer(): void
    {
        $this->http->fake(['*' => $this->http->response($this->document(['token_endpoint' => 'http://idp.example.test/token']))]);

        $this->expectException(SsoException::class);

        $this->client->discover($this->provider());
    }

    public function test_microsoft_discovery_uses_the_tenant_template(): void
    {
        $issuer = 'https://login.microsoftonline.com/common/v2.0';
        $this->http->fake(['*' => $this->http->response([
            ...$this->document(),
            'issuer' => OidcDiscoveryClient::MICROSOFT_ISSUER_TEMPLATE,
        ])]);

        $document = $this->client->discover($this->provider(SsoProviderType::MICROSOFT, $issuer));

        $this->assertSame(OidcDiscoveryClient::MICROSOFT_ISSUER_TEMPLATE, $document['issuer']);
    }

    public function test_an_unknown_key_refreshes_the_key_set_at_most_once_a_minute(): void
    {
        $this->http->fake([
            self::ISSUER.'/.well-known/openid-configuration' => $this->http->response($this->document()),
            self::ISSUER.'/jwks' => $this->http->response(['keys' => [['kid' => 'current', 'kty' => 'RSA']]]),
        ]);

        $this->assertSame('current', $this->client->findSigningKey($this->provider(), 'current')['kid']);
        $this->assertNull($this->client->findSigningKey($this->provider(), 'rotated'));
        $this->assertNull($this->client->findSigningKey($this->provider(), 'rotated'));

        $this->http->assertSentCount(2);
    }

    public function test_an_unreachable_provider_is_reported_as_unavailable(): void
    {
        $this->http->fake(['*' => $this->http->response('down', 503)]);

        try {
            $this->client->discover($this->provider());
            $this->fail('Expected the provider to be unavailable');
        } catch (SsoException $exception) {
            $this->assertSame(SsoErrorCode::PROVIDER_UNAVAILABLE, $exception->errorCode);
        }
    }

    public function test_a_restricted_issuer_that_resolves_to_a_metadata_address_is_never_requested(): void
    {
        $this->resolver->shouldReceive('resolve')->with('idp.example.test')->andReturn(['169.254.169.254']);
        $this->http->fake(['*' => $this->http->response($this->document())]);

        try {
            $this->client->discover($this->restrictedProvider());
            $this->fail('Expected the issuer to be refused');
        } catch (SsoException $exception) {
            $this->assertSame(SsoErrorCode::ISSUER_NOT_ALLOWED, $exception->errorCode);
        }

        $this->http->assertNothingSent();
    }

    public function test_a_restricted_issuer_must_use_https(): void
    {
        $this->http->fake(['*' => $this->http->response($this->document(['issuer' => 'http://idp.example.test']))]);

        $this->expectException(SsoException::class);

        $this->client->discover($this->restrictedProvider(issuer: 'http://idp.example.test'));
    }

    public function test_a_key_set_on_a_private_address_is_refused(): void
    {
        $this->resolver->shouldReceive('resolve')->with('keys.internal.test')->andReturn(['10.0.0.5']);
        $this->http->fake([
            self::ISSUER.'/.well-known/openid-configuration' => $this->http->response($this->document(['jwks_uri' => 'https://keys.internal.test/jwks'])),
            '*' => $this->http->response(['keys' => []]),
        ]);

        try {
            $this->client->findSigningKey($this->restrictedProvider(), 'kid');
            $this->fail('Expected the key set to be refused');
        } catch (SsoException $exception) {
            $this->assertSame(SsoErrorCode::ISSUER_NOT_ALLOWED, $exception->errorCode);
        }

        $this->http->assertSentCount(1);
    }

    public function test_a_tenant_connection_expects_its_own_issuer_rather_than_the_template(): void
    {
        $issuer = 'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0';
        $this->http->fake(['*' => $this->http->response($this->document([
            'issuer' => $issuer,
            'authorization_endpoint' => 'https://login.microsoftonline.com/authorize',
            'token_endpoint' => 'https://login.microsoftonline.com/token',
            'jwks_uri' => 'https://login.microsoftonline.com/keys',
        ]))]);

        $document = $this->client->discover(new SsoProviderConfig(
            key: 'connection-1',
            type: SsoProviderType::MICROSOFT,
            name: 'Microsoft Entra ID',
            issuer: $issuer,
            clientId: 'client',
            clientSecret: 'secret',
            logoUrl: null,
            trustEmail: false,
            restrictNetwork: true,
            connectionId: 1,
            accountId: 1,
            tenantId: '11111111-2222-3333-4444-555555555555',
        ));

        $this->assertSame($issuer, $document['issuer']);
    }

    private function restrictedProvider(string $issuer = self::ISSUER): SsoProviderConfig
    {
        return new SsoProviderConfig('test', SsoProviderType::OIDC, 'Test', $issuer, 'client', 'secret', null, false, restrictNetwork: true);
    }

    private function document(array $overrides = []): array
    {
        return [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::ISSUER.'/authorize',
            'token_endpoint' => self::ISSUER.'/token',
            'jwks_uri' => self::ISSUER.'/jwks',
            ...$overrides,
        ];
    }

    private function provider(SsoProviderType $type = SsoProviderType::OIDC, string $issuer = self::ISSUER): SsoProviderConfig
    {
        return new SsoProviderConfig('test', $type, 'Test', $issuer, 'client', 'secret', null, true);
    }
}
