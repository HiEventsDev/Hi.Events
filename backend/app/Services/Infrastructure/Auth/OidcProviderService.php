<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OidcProviderService
{
    private const DISCOVERY_CACHE_KEY = 'oidc.discovery';
    private const JWKS_CACHE_KEY = 'oidc.jwks';

    public function __construct(
        private readonly Client           $httpClient,
        private readonly CacheRepository  $cache,
    )
    {
    }

    public function isEnabled(): bool
    {
        return (bool)config('oidc.enabled');
    }

    public function getAuthorizationUrl(string $state, string $nonce): string
    {
        $config = $this->getDiscoveryDocument();

        $queryParams = [
            'client_id' => config('oidc.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('oidc.redirect_uri'),
            'scope' => implode(' ', config('oidc.scopes', [])),
            'state' => $state,
            'nonce' => $nonce,
        ];

        if (config('oidc.audience')) {
            $queryParams['audience'] = config('oidc.audience');
        }

        $query = http_build_query($queryParams);

        return Arr::get($config, 'authorization_endpoint') . '?' . $query;
    }

    /**
     * @throws GuzzleException
     */
    public function exchangeCode(string $code): array
    {
        $tokenEndpoint = Arr::get($this->getDiscoveryDocument(), 'token_endpoint');

        $response = $this->httpClient->post($tokenEndpoint, [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('oidc.redirect_uri'),
                'client_id' => config('oidc.client_id'),
                'client_secret' => config('oidc.client_secret'),
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function validateIdToken(string $idToken, string $nonce): object
    {
        $jwks = $this->getJwks();
        $keys = JWK::parseKeySet($jwks);
        $decoded = JWT::decode($idToken, $keys);

        $this->assertClaim($decoded->iss ?? null, config('oidc.issuer'), 'iss');
        $this->assertAudience($decoded->aud ?? null, config('oidc.client_id'));
        $this->assertClaim($decoded->nonce ?? null, $nonce, 'nonce');

        return $decoded;
    }

    private function getDiscoveryDocument(): array
    {
        $issuer = rtrim((string)config('oidc.issuer'), '/');

        if (empty($issuer)) {
            throw new RuntimeException('OIDC issuer is not configured');
        }

        $cacheKey = sprintf('%s.%s', self::DISCOVERY_CACHE_KEY, md5($issuer));

        return $this->cache->remember(
            $cacheKey,
            (int)config('oidc.jwks_cache_ttl', 300),
            function () use ($issuer) {
                try {
                    $response = $this->httpClient->get(
                        $issuer . '/.well-known/openid-configuration',
                        ['headers' => ['Accept' => 'application/json']]
                    );
                } catch (GuzzleException $exception) {
                    Log::error('Failed to fetch OIDC discovery document', ['message' => $exception->getMessage()]);
                    throw $exception;
                }

                return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            }
        );
    }

    private function getJwks(): array
    {
        $config = $this->getDiscoveryDocument();
        $jwksUri = Arr::get($config, 'jwks_uri');

        if ($jwksUri === null) {
            throw new RuntimeException('OIDC provider does not expose a JWKS URI');
        }

        $cacheKey = sprintf('%s.%s', self::JWKS_CACHE_KEY, md5($jwksUri));

        return $this->cache->remember(
            $cacheKey,
            (int)config('oidc.jwks_cache_ttl', 300),
            function () use ($jwksUri) {
                try {
                    $response = $this->httpClient->get($jwksUri, ['headers' => ['Accept' => 'application/json']]);
                } catch (GuzzleException $exception) {
                    Log::error('Failed to fetch OIDC JWKS', ['message' => $exception->getMessage()]);
                    throw $exception;
                }

                return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            }
        );
    }

    private function assertClaim(mixed $actual, mixed $expected, string $claim): void
    {
        if ($actual === $expected) {
            return;
        }

        throw new RuntimeException("Invalid {$claim} claim on ID token");
    }

    private function assertAudience(mixed $audienceClaim, string $expectedClientId): void
    {
        if (is_array($audienceClaim) && in_array($expectedClientId, $audienceClaim, true)) {
            return;
        }

        if ($audienceClaim === $expectedClientId) {
            return;
        }

        throw new RuntimeException('Invalid aud claim on ID token');
    }
}
