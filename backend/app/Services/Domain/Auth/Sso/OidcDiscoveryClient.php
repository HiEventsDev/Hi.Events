<?php

namespace HiEvents\Services\Domain\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoProviderType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\Network\SsrfGuard;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class OidcDiscoveryClient
{
    public const MICROSOFT_ISSUER_TEMPLATE = 'https://login.microsoftonline.com/{tenantid}/v2.0';

    private const CACHE_SECONDS = 86400;

    private const JWKS_REFRESH_COOLDOWN_SECONDS = 60;

    private const MAX_RESPONSE_BYTES = 1048576;

    public function __construct(
        private readonly HttpClient $http,
        private readonly Cache $cache,
        private readonly SsrfGuard $ssrfGuard,
    ) {}

    /**
     * @throws SsoException
     */
    public function discover(SsoProviderConfig $provider): array
    {
        $cacheKey = 'sso_discovery:'.hash('sha256', $provider->issuer);
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $document = $this->getJson($provider, rtrim($provider->issuer, '/').'/.well-known/openid-configuration');

        $this->assertDiscoveryDocument($provider, $document);

        $this->cache->put($cacheKey, $document, self::CACHE_SECONDS);

        return $document;
    }

    /**
     * @throws SsoException
     */
    public function findSigningKey(SsoProviderConfig $provider, string $keyId): ?array
    {
        $jwksUri = $this->discover($provider)['jwks_uri'];
        $cacheKey = 'sso_jwks:'.hash('sha256', $jwksUri);

        $key = $this->findKey($this->cache->get($cacheKey), $keyId);

        if ($key !== null) {
            return $key;
        }

        if (! $this->cache->add($cacheKey.':refreshed', true, self::JWKS_REFRESH_COOLDOWN_SECONDS)) {
            return null;
        }

        $keys = $this->getJson($provider, $jwksUri);
        $this->cache->put($cacheKey, $keys, self::CACHE_SECONDS);

        return $this->findKey($keys, $keyId);
    }

    /**
     * @throws SsoException
     */
    public function post(SsoProviderConfig $provider, string $url, array $form, ?array $basicAuth): array
    {
        $request = $this->request($provider, $url)->asForm();

        if ($basicAuth !== null) {
            $request = $request->withBasicAuth(...$basicAuth);
        }

        try {
            $response = $request->post($url, $form);
        } catch (ConnectionException $exception) {
            throw new SsoException(SsoErrorCode::PROVIDER_UNAVAILABLE, $exception->getMessage(), previous: $exception);
        }

        if ($response->serverError()) {
            throw new SsoException(SsoErrorCode::PROVIDER_UNAVAILABLE, 'Token endpoint returned '.$response->status());
        }

        if ($response->failed()) {
            $error = $response->json('error');

            throw $error === 'invalid_grant'
                ? new SsoException(SsoErrorCode::STATE_INVALID, __('This sign-in link has expired. Please try again.'))
                : new SsoException(SsoErrorCode::TOKEN_INVALID, 'Token endpoint rejected the request: '.$response->status().' '.(is_string($error) ? $error : 'unknown'));
        }

        return $this->decode($response);
    }

    /**
     * @throws SsoException
     */
    private function getJson(SsoProviderConfig $provider, string $url): array
    {
        $request = $this->request($provider, $url);

        try {
            $response = $request->get($url);
        } catch (ConnectionException $exception) {
            throw new SsoException(SsoErrorCode::PROVIDER_UNAVAILABLE, $exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            throw new SsoException(SsoErrorCode::PROVIDER_UNAVAILABLE, 'Identity provider returned '.$response->status());
        }

        return $this->decode($response);
    }

    /**
     * @throws SsoException
     */
    private function decode(Response $response): array
    {
        $body = $response->body();

        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new SsoException(SsoErrorCode::PROVIDER_UNAVAILABLE, 'Identity provider response too large');
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new SsoException(SsoErrorCode::PROVIDER_UNAVAILABLE, 'Identity provider returned invalid JSON');
        }

        return $decoded;
    }

    /**
     * @throws SsoException
     */
    private function request(SsoProviderConfig $provider, string $url): PendingRequest
    {
        $options = ['allow_redirects' => false];

        if ($provider->restrictNetwork) {
            $options['curl'] = [CURLOPT_RESOLVE => [$this->ssrfGuard->pin($url)->curlResolveEntry()]];
        }

        return $this->http
            ->connectTimeout(3)
            ->timeout(5)
            ->withOptions($options)
            ->acceptJson();
    }

    /**
     * @throws SsoException
     */
    private function assertDiscoveryDocument(SsoProviderConfig $provider, array $document): void
    {
        $expectedIssuer = $provider->type === SsoProviderType::MICROSOFT && $provider->tenantId === null
            ? self::MICROSOFT_ISSUER_TEMPLATE
            : $provider->issuer;

        $endpoints = [
            $document['authorization_endpoint'] ?? null,
            $document['token_endpoint'] ?? null,
            $document['jwks_uri'] ?? null,
        ];

        $documentIssuer = $document['issuer'] ?? null;
        $issuerMatches = $provider->type === SsoProviderType::OIDC
            ? is_string($documentIssuer) && rtrim($documentIssuer, '/') === $expectedIssuer
            : $documentIssuer === $expectedIssuer;

        if (! $issuerMatches) {
            throw new SsoException(SsoErrorCode::PROVIDER_UNAVAILABLE, 'Discovery issuer does not match the configured issuer');
        }

        foreach ($endpoints as $endpoint) {
            if (! is_string($endpoint) || ! $this->isAllowedUrl($provider, $endpoint)) {
                throw new SsoException(SsoErrorCode::PROVIDER_UNAVAILABLE, 'Discovery document is missing a valid endpoint');
            }
        }
    }

    private function isAllowedUrl(SsoProviderConfig $provider, string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return $scheme === 'https'
            || (! $provider->restrictNetwork && $scheme === 'http' && parse_url($provider->issuer, PHP_URL_SCHEME) === 'http');
    }

    private function findKey(mixed $jwks, string $keyId): ?array
    {
        if (! is_array($jwks) || ! is_array($jwks['keys'] ?? null)) {
            return null;
        }

        foreach ($jwks['keys'] as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $keyId && ($key['use'] ?? 'sig') === 'sig') {
                return $key;
            }
        }

        return null;
    }
}
