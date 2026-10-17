<?php

namespace Tests\Concerns;

use DateTimeImmutable;
use HiEvents\Services\Domain\Auth\Sso\Network\HostResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

trait FakesOidcProvider
{
    protected const OIDC_ISSUER = 'https://idp.example.test';

    protected const OIDC_CLIENT_ID = 'hi-events-client';

    private static ?array $oidcKeyPair = null;

    protected array $oidcClaims = [];

    protected array $oidcNonces = [];

    protected ?array $lastTokenRequest = null;

    public array $fakeDnsAddresses = [];

    public array $fakeTxtRecords = [];

    protected function fakeDns(): void
    {
        config()->set('sso.allow_private_issuers', false);
        $test = $this;

        $this->app->instance(HostResolver::class, new class($test) extends HostResolver
        {
            public function __construct(private readonly object $test) {}

            public function resolve(string $host): array
            {
                return $this->test->fakeDnsAddresses[$host] ?? ['93.184.216.34'];
            }

            public function txtRecords(string $name): array
            {
                return $this->test->fakeTxtRecords[$name] ?? [];
            }
        });
    }

    protected function fakeOidcProvider(bool $trustEmail = true, string $key = 'oidc'): void
    {
        $this->fakeDns();

        config()->set("sso.providers.$key", [
            'type' => 'OIDC',
            'name' => 'Example SSO',
            'issuer' => self::OIDC_ISSUER,
            'client_id' => self::OIDC_CLIENT_ID,
            'client_secret' => 'client-secret',
            'logo_url' => null,
            'trust_email' => $trustEmail,
        ]);

        Http::fake([
            self::OIDC_ISSUER.'/.well-known/openid-configuration' => Http::response([
                'issuer' => self::OIDC_ISSUER,
                'authorization_endpoint' => self::OIDC_ISSUER.'/authorize',
                'token_endpoint' => self::OIDC_ISSUER.'/token',
                'jwks_uri' => self::OIDC_ISSUER.'/jwks',
                'token_endpoint_auth_methods_supported' => ['client_secret_basic'],
            ]),
            self::OIDC_ISSUER.'/jwks' => Http::response(['keys' => [$this->oidcPublicJwk()]]),
            self::OIDC_ISSUER.'/token' => function (Request $request) {
                $this->lastTokenRequest = $request->data();

                return Http::response([
                    'access_token' => 'access-token',
                    'token_type' => 'Bearer',
                    'id_token' => $this->signOidcToken($this->oidcClaims),
                ]);
            },
        ]);
    }

    protected function signedInAs(array $claims): void
    {
        $this->oidcClaims = $claims;
    }

    /**
     * @return array{state: string, nonce: string, binding: string}
     */
    protected function startOidcFlow(array $payload = ['intent' => 'LOGIN'], string $provider = 'oidc'): array
    {
        $response = $this->postJson("/auth/sso/$provider/start", $payload)->assertOk();

        return $this->flowFrom($response->json('data'));
    }

    protected function flowFrom(array $startResponse): array
    {
        parse_str((string) parse_url($startResponse['authorization_url'], PHP_URL_QUERY), $query);
        $this->oidcNonces[$query['state']] = $query['nonce'];

        return [
            'state' => $query['state'],
            'nonce' => $query['nonce'],
            'binding' => $startResponse['binding'],
            'query' => $query,
        ];
    }

    protected function exchangeOidcCode(array $flow, array $claims, string $uri = '/auth/sso/exchange', array $headers = [])
    {
        $this->signedInAs([
            'nonce' => $flow['nonce'],
            ...$claims,
        ]);

        return $this->postJson($uri, [
            'code' => 'authorization-code',
            'state' => $flow['state'],
            'binding' => $flow['binding'],
        ], $headers);
    }

    protected function signOidcToken(array $claims, array $headers = ['kid' => 'test-key']): string
    {
        $configuration = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($this->oidcKeyPair()['private']),
            InMemory::plainText($this->oidcKeyPair()['public']),
        );

        $now = new DateTimeImmutable;
        $builder = $configuration->builder()
            ->issuedBy($claims['iss'] ?? self::OIDC_ISSUER)
            ->permittedFor(...(array) ($claims['aud'] ?? self::OIDC_CLIENT_ID))
            ->relatedTo($claims['sub'] ?? 'subject-1')
            ->issuedAt($now)
            ->expiresAt($claims['exp'] ?? $now->modify('+5 minutes'));

        foreach ($headers as $name => $value) {
            $builder = $builder->withHeader($name, $value);
        }

        foreach ($claims as $name => $value) {
            if (! in_array($name, ['iss', 'aud', 'sub', 'exp', 'iat'], true)) {
                $builder = $builder->withClaim($name, $value);
            }
        }

        return $builder->getToken($configuration->signer(), $configuration->signingKey())->toString();
    }

    protected function oidcPublicJwk(string $kid = 'test-key'): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($this->oidcKeyPair()['public']));

        return [
            'kty' => 'RSA',
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ];
    }

    protected function oidcKeyPair(): array
    {
        if (self::$oidcKeyPair === null) {
            $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
            openssl_pkey_export($key, $private);
            self::$oidcKeyPair = [
                'private' => $private,
                'public' => openssl_pkey_get_details($key)['key'],
            ];
        }

        return self::$oidcKeyPair;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
