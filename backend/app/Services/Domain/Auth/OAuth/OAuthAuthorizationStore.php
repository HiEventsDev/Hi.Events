<?php

namespace HiEvents\Services\Domain\Auth\OAuth;

use HiEvents\Services\Domain\Auth\OAuth\DTO\AuthorizationCodeDTO;
use HiEvents\Services\Domain\Auth\OAuth\DTO\AuthorizationRequestDTO;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

class OAuthAuthorizationStore
{
    public const REQUEST_ID_LENGTH = 40;

    public const REQUEST_ID_PATTERN = '[A-Za-z0-9]{40}';

    public const REQUEST_TTL_SECONDS = 600;

    public const CODE_TTL_SECONDS = 60;

    public const CODE_REPLAY_TTL_SECONDS = 600;

    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function storeRequest(AuthorizationRequestDTO $request): string
    {
        $id = Str::random(self::REQUEST_ID_LENGTH);

        $this->cache->put($this->requestKey($id), $request->toArray(), self::REQUEST_TTL_SECONDS);

        return $id;
    }

    public function findRequest(string $id): ?AuthorizationRequestDTO
    {
        $payload = $this->cache->get($this->requestKey($id));

        return is_array($payload) ? AuthorizationRequestDTO::from($payload) : null;
    }

    public function claimRequest(string $id): bool
    {
        $key = $this->requestKey($id);

        if (! $this->cache->has($key) || ! $this->cache->add($key.':claimed', true, self::REQUEST_TTL_SECONDS)) {
            return false;
        }

        $this->cache->forget($key);

        return true;
    }

    public function issueCode(AuthorizationCodeDTO $code): string
    {
        $secret = Str::random(64);

        $this->cache->put($this->codeKey($secret), $code->toArray(), self::CODE_TTL_SECONDS);

        return $secret;
    }

    public function consumeCode(string $secret): ?AuthorizationCodeDTO
    {
        $key = $this->codeKey($secret);
        $payload = $this->cache->get($key);

        if (! is_array($payload) || ! $this->cache->add($key.':consumed', true, self::CODE_REPLAY_TTL_SECONDS)) {
            return null;
        }

        $this->cache->forget($key);

        return AuthorizationCodeDTO::from($payload);
    }

    public function rememberConnectionForCode(string $secret, int $connectionId): void
    {
        $this->cache->put($this->codeKey($secret).':connection', $connectionId, self::CODE_REPLAY_TTL_SECONDS);
    }

    public function connectionForReplayedCode(string $secret): ?int
    {
        $connectionId = $this->cache->get($this->codeKey($secret).':connection');

        return is_int($connectionId) ? $connectionId : null;
    }

    private function requestKey(string $id): string
    {
        return 'oauth:authorization_request:'.hash('sha256', $id);
    }

    private function codeKey(string $secret): string
    {
        return 'oauth:code:'.hash('sha256', $secret);
    }
}
