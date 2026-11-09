<?php

namespace HiEvents\Services\Domain\Auth\OAuth;

class OAuthRedirectUriPolicy
{
    public const MAX_REDIRECT_URIS = 10;

    public const VERIFIED_HOSTS = [
        'claude.ai',
        'claude.com',
        'chatgpt.com',
        'chat.openai.com',
    ];

    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]', '::1'];

    private const BLOCKED_SCHEMES = ['javascript', 'data', 'file', 'vbscript', 'blob', 'about', 'ftp', 'ws', 'wss'];

    public function isAllowed(string $uri): bool
    {
        $parts = parse_url($uri);

        if ($parts === false || ! isset($parts['scheme']) || isset($parts['fragment']) || strlen($uri) > 2048) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        return match (true) {
            $scheme === 'https' => isset($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass']),
            $scheme === 'http' => $this->isLoopback($parts),
            default => preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) === 1 && ! in_array($scheme, self::BLOCKED_SCHEMES, true),
        };
    }

    /**
     * @param  string[]  $registeredUris
     */
    public function matches(array $registeredUris, string $requestedUri): bool
    {
        foreach ($registeredUris as $registeredUri) {
            if ($registeredUri === $requestedUri || $this->matchesLoopbackIgnoringPort($registeredUri, $requestedUri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string[]  $redirectUris
     */
    public function isVerified(array $redirectUris): bool
    {
        if ($redirectUris === []) {
            return false;
        }

        foreach ($redirectUris as $uri) {
            $parts = parse_url($uri);

            if (strtolower($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), self::VERIFIED_HOSTS, true)) {
                return false;
            }
        }

        return true;
    }

    public function describe(string $uri): string
    {
        $parts = parse_url($uri) ?: [];

        if ($this->isLoopback($parts)) {
            return 'localhost';
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if ($scheme === 'https') {
            return $parts['host'] ?? '';
        }

        return $scheme.'://'.($parts['host'] ?? '');
    }

    private function matchesLoopbackIgnoringPort(string $registeredUri, string $requestedUri): bool
    {
        $registered = parse_url($registeredUri);
        $requested = parse_url($requestedUri);

        if (! is_array($registered) || ! is_array($requested) || ! $this->isLoopback($registered) || ! $this->isLoopback($requested)) {
            return false;
        }

        return strtolower($registered['host']) === strtolower($requested['host'])
            && ($registered['path'] ?? '') === ($requested['path'] ?? '')
            && ($registered['query'] ?? '') === ($requested['query'] ?? '');
    }

    private function isLoopback(array $parts): bool
    {
        return in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && in_array(strtolower($parts['host'] ?? ''), self::LOOPBACK_HOSTS, true);
    }
}
