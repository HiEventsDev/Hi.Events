<?php

namespace HiEvents\Services\Domain\Auth\OAuth;

use HiEvents\DomainObjects\Enums\OAuthScope;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;

class OAuthEndpoints
{
    public const MCP_PATH = 'mcp';

    private const FORWARDED_PREFIX_HEADER = 'X-Forwarded-Prefix';

    public function __construct(
        private readonly UrlGenerator $urlGenerator,
        private readonly Request $request,
    ) {}

    public function issuer(): string
    {
        $root = rtrim($this->urlGenerator->to('/'), '/');
        $prefix = $this->request->isFromTrustedProxy()
            ? trim((string) $this->request->headers->get(self::FORWARDED_PREFIX_HEADER), '/')
            : '';

        return $prefix === '' || str_ends_with($root, '/'.$prefix) ? $root : $root.'/'.$prefix;
    }

    public function resource(): string
    {
        return $this->url(self::MCP_PATH);
    }

    public function protectedResourceMetadataUrl(): string
    {
        return $this->url('.well-known/oauth-protected-resource/'.self::MCP_PATH);
    }

    public function consentUrl(array $query): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/connect?'.http_build_query($query);
    }

    public function clientRedirect(string $redirectUri, array $params): string
    {
        $query = http_build_query(array_filter([...$params, 'iss' => $this->issuer()], static fn (mixed $value): bool => $value !== null));

        return $redirectUri.(str_contains($redirectUri, '?') ? '&' : '?').$query;
    }

    public function isResource(?string $resource): bool
    {
        return $resource === null || rtrim($resource, '/') === $this->resource();
    }

    public function protectedResourceMetadata(): array
    {
        return [
            'resource' => $this->resource(),
            'authorization_servers' => [$this->issuer()],
            'scopes_supported' => OAuthScope::valuesArray(),
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'Hi.Events',
        ];
    }

    public function authorizationServerMetadata(): array
    {
        return [
            'issuer' => $this->issuer(),
            'authorization_endpoint' => $this->url('oauth/authorize'),
            'token_endpoint' => $this->url('oauth/token'),
            'registration_endpoint' => $this->url('oauth/register'),
            'revocation_endpoint' => $this->url('oauth/revoke'),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => OAuthScope::valuesArray(),
            'authorization_response_iss_parameter_supported' => true,
        ];
    }

    private function url(string $path): string
    {
        return $this->issuer().'/'.$path;
    }
}
