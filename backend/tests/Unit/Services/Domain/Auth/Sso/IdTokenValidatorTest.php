<?php

namespace Tests\Unit\Services\Domain\Auth\Sso;

use Carbon\CarbonImmutable;
use Carbon\FactoryImmutable;
use HiEvents\DomainObjects\Enums\SsoProviderType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\IdTokenValidator;
use HiEvents\Services\Domain\Auth\Sso\Jwk\JwkConverter;
use HiEvents\Services\Domain\Auth\Sso\OidcDiscoveryClient;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256 as HmacSha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;
use Mockery as m;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesOidcProvider;
use Tests\TestCase;

class IdTokenValidatorTest extends TestCase
{
    use FakesOidcProvider;

    private OidcDiscoveryClient $discoveryClient;

    private IdTokenValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discoveryClient = m::mock(OidcDiscoveryClient::class);
        $this->discoveryClient->shouldReceive('findSigningKey')->with(m::any(), 'test-key')->andReturn($this->oidcPublicJwk());
        $this->discoveryClient->shouldReceive('findSigningKey')->andReturnNull();
        $this->validator = new IdTokenValidator($this->discoveryClient, new JwkConverter, FactoryImmutable::getDefaultInstance());
    }

    public function test_accepts_a_valid_token(): void
    {
        $claims = $this->validator->validate($this->provider(), $this->signOidcToken($this->claims()), 'nonce-1', null);

        $this->assertSame('subject-1', $claims['sub']);
        $this->assertSame([self::OIDC_CLIENT_ID], $claims['aud']);
        $this->assertIsInt($claims['exp']);
    }

    public function test_accepts_an_issuer_published_with_a_trailing_slash(): void
    {
        $claims = $this->validator->validate($this->provider(), $this->signOidcToken([...$this->claims(), 'iss' => self::OIDC_ISSUER.'/']), 'nonce-1', null);

        $this->assertSame(self::OIDC_ISSUER.'/', $claims['iss']);
    }

    #[DataProvider('invalidClaims')]
    public function test_rejects_tampered_claims(array $overrides): void
    {
        $this->expectException(SsoException::class);

        $this->validator->validate($this->provider(), $this->signOidcToken([...$this->claims(), ...$overrides]), 'nonce-1', null);
    }

    public static function invalidClaims(): array
    {
        return [
            'wrong issuer' => [['iss' => 'https://attacker.example.test']],
            'wrong audience' => [['aud' => 'another-client']],
            'other party authorised' => [['aud' => [self::OIDC_CLIENT_ID, 'another-client'], 'azp' => 'another-client']],
            'several audiences without azp' => [['aud' => [self::OIDC_CLIENT_ID, 'another-client']]],
            'expired' => [['exp' => new \DateTimeImmutable('-10 minutes')]],
            'wrong nonce' => [['nonce' => 'nonce-2']],
            'missing nonce' => [['nonce' => null]],
        ];
    }

    public function test_rejects_an_unknown_signing_key(): void
    {
        $this->expectException(SsoException::class);

        $this->validator->validate($this->provider(), $this->signOidcToken($this->claims(), ['kid' => 'unknown']), 'nonce-1', null);
    }

    public function test_rejects_a_token_signed_with_a_shared_secret(): void
    {
        $token = (new Builder(new JoseEncoder, ChainedFormatter::default()))
            ->issuedBy(self::OIDC_ISSUER)
            ->permittedFor(self::OIDC_CLIENT_ID)
            ->relatedTo('subject-1')
            ->issuedAt(new \DateTimeImmutable)
            ->expiresAt(new \DateTimeImmutable('+5 minutes'))
            ->withClaim('nonce', 'nonce-1')
            ->withHeader('kid', 'test-key')
            ->getToken(new HmacSha256, InMemory::plainText(str_repeat('k', 32)))
            ->toString();

        $this->expectException(SsoException::class);

        $this->validator->validate($this->provider(), $token, 'nonce-1', null);
    }

    public function test_rejects_an_unsigned_token(): void
    {
        $encode = static fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        $token = $encode(['alg' => 'none', 'kid' => 'test-key']).'.'.$encode([
            'iss' => self::OIDC_ISSUER,
            'aud' => self::OIDC_CLIENT_ID,
            'sub' => 'subject-1',
            'nonce' => 'nonce-1',
            'iat' => time(),
            'exp' => time() + 300,
        ]).'.';

        $this->expectException(SsoException::class);

        $this->validator->validate($this->provider(), $token, 'nonce-1', null);
    }

    public function test_checks_the_access_token_hash_when_present(): void
    {
        $hash = rtrim(strtr(base64_encode(substr(hash('sha256', 'access-token', true), 0, 16)), '+/', '-_'), '=');
        $token = $this->signOidcToken([...$this->claims(), 'at_hash' => $hash]);

        $this->assertSame('subject-1', $this->validator->validate($this->provider(), $token, 'nonce-1', 'access-token')['sub']);

        $this->expectException(SsoException::class);
        $this->validator->validate($this->provider(), $token, 'nonce-1', 'another-access-token');
    }

    public function test_accepts_both_google_issuer_forms(): void
    {
        $google = $this->provider(SsoProviderType::GOOGLE, 'https://accounts.google.com');

        foreach (['https://accounts.google.com', 'accounts.google.com'] as $issuer) {
            $claims = $this->validator->validate($google, $this->signOidcToken([...$this->claims(), 'iss' => $issuer]), 'nonce-1', null);
            $this->assertSame($issuer, $claims['iss']);
        }
    }

    public function test_requires_the_microsoft_issuer_to_match_the_token_tenant(): void
    {
        $microsoft = $this->provider(SsoProviderType::MICROSOFT, 'https://login.microsoftonline.com/common/v2.0');
        $tenant = '11111111-2222-3333-4444-555555555555';

        $claims = $this->validator->validate($microsoft, $this->signOidcToken([
            ...$this->claims(),
            'iss' => "https://login.microsoftonline.com/$tenant/v2.0",
            'tid' => $tenant,
        ]), 'nonce-1', null);
        $this->assertSame($tenant, $claims['tid']);

        $this->expectException(SsoException::class);
        $this->validator->validate($microsoft, $this->signOidcToken([
            ...$this->claims(),
            'iss' => 'https://login.microsoftonline.com/99999999-2222-3333-4444-555555555555/v2.0',
            'tid' => $tenant,
        ]), 'nonce-1', null);
    }

    public function test_allows_a_minute_of_clock_skew(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(330));

        try {
            $claims = $this->validator->validate($this->provider(), $this->signOidcToken($this->claims()), 'nonce-1', null);
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame('subject-1', $claims['sub']);
    }

    public function test_an_entra_connection_requires_its_own_tenant(): void
    {
        $tenant = '11111111-2222-3333-4444-555555555555';
        $issuer = "https://login.microsoftonline.com/$tenant/v2.0";
        $provider = $this->connection(SsoProviderType::MICROSOFT, $issuer, tenantId: $tenant);

        $claims = $this->validator->validate($provider, $this->signOidcToken([...$this->claims(), 'iss' => $issuer, 'tid' => $tenant]), 'nonce-1', null);
        $this->assertSame($tenant, $claims['tid']);

        $otherTenant = '99999999-2222-3333-4444-555555555555';
        $this->expectException(SsoException::class);

        $this->validator->validate(
            $provider,
            $this->signOidcToken([...$this->claims(), 'iss' => "https://login.microsoftonline.com/$otherTenant/v2.0", 'tid' => $otherTenant]),
            'nonce-1',
            null,
        );
    }

    public function test_a_workspace_connection_requires_its_hosted_domain(): void
    {
        $provider = $this->connection(SsoProviderType::GOOGLE, 'https://accounts.google.com', hostedDomain: 'acme.test');

        $claims = $this->validator->validate($provider, $this->signOidcToken([...$this->claims(), 'iss' => 'https://accounts.google.com', 'hd' => 'ACME.test']), 'nonce-1', null);
        $this->assertSame('ACME.test', $claims['hd']);

        foreach ([['hd' => 'other.test'], []] as $hostedDomainClaim) {
            try {
                $this->validator->validate($provider, $this->signOidcToken([...$this->claims(), 'iss' => 'https://accounts.google.com', ...$hostedDomainClaim]), 'nonce-1', null);
                $this->fail('Expected a token from another domain to be rejected');
            } catch (SsoException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function connection(SsoProviderType $type, string $issuer, ?string $tenantId = null, ?string $hostedDomain = null): SsoProviderConfig
    {
        return new SsoProviderConfig(
            key: 'connection-1',
            type: $type,
            name: 'Connection',
            issuer: $issuer,
            clientId: self::OIDC_CLIENT_ID,
            clientSecret: 'secret',
            logoUrl: null,
            trustEmail: false,
            connectionId: 1,
            accountId: 1,
            tenantId: $tenantId,
            hostedDomain: $hostedDomain,
        );
    }

    private function claims(): array
    {
        return ['sub' => 'subject-1', 'nonce' => 'nonce-1'];
    }

    private function provider(SsoProviderType $type = SsoProviderType::OIDC, string $issuer = self::OIDC_ISSUER): SsoProviderConfig
    {
        return new SsoProviderConfig(
            key: 'test',
            type: $type,
            name: 'Test',
            issuer: $issuer,
            clientId: self::OIDC_CLIENT_ID,
            clientSecret: 'secret',
            logoUrl: null,
            trustEmail: true,
        );
    }
}
