<?php

namespace HiEvents\Services\Domain\Auth\TwoFactor;

use HiEvents\Services\Domain\Auth\TwoFactor\DTO\TwoFactorChallengeDTO;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

class TwoFactorChallengeService
{
    public const TTL_SECONDS = 600;

    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function create(int $userId): string
    {
        $token = Str::random(64);

        $this->store($token, new TwoFactorChallengeDTO(
            userId: $userId,
            verified: false,
        ));

        return $token;
    }

    public function find(string $token): ?TwoFactorChallengeDTO
    {
        $data = $this->cache->get($this->key($token));

        return is_array($data) ? TwoFactorChallengeDTO::from($data) : null;
    }

    public function markVerified(string $token, TwoFactorChallengeDTO $challenge, ?int $recoveryCodesRemaining): TwoFactorChallengeDTO
    {
        $verified = new TwoFactorChallengeDTO(
            userId: $challenge->userId,
            verified: true,
            recoveryCodesRemaining: $recoveryCodesRemaining,
        );

        $this->store($token, $verified);

        return $verified;
    }

    public function forget(string $token): void
    {
        $this->cache->forget($this->key($token));
    }

    private function store(string $token, TwoFactorChallengeDTO $challenge): void
    {
        $this->cache->put($this->key($token), $challenge->toArray(), self::TTL_SECONDS);
    }

    private function key(string $token): string
    {
        return 'two_factor_challenge:'.hash('sha256', $token);
    }
}
