<?php

namespace HiEvents\Services\Domain\Auth\TwoFactor;

use HiEvents\Repository\Interfaces\UserTrustedDeviceRepositoryInterface;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    public const COOKIE_NAME = 'hi_trusted_device';

    public const TRUST_DAYS = 30;

    private const USER_AGENT_MAX_LENGTH = 512;

    public function __construct(
        private readonly UserTrustedDeviceRepositoryInterface $trustedDeviceRepository,
    ) {}

    public function trust(int $userId, ?string $userAgent, ?string $ipAddress): string
    {
        $token = Str::random(64);

        $this->trustedDeviceRepository->create([
            'user_id' => $userId,
            'token_hash' => $this->hash($token),
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, self::USER_AGENT_MAX_LENGTH, '') : null,
            'ip_address' => $ipAddress,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::TRUST_DAYS),
        ]);

        return $token;
    }

    public function isTrusted(int $userId, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $updated = $this->trustedDeviceRepository->updateWhere(
            attributes: ['last_used_at' => now()],
            where: [
                'user_id' => $userId,
                'token_hash' => $this->hash($token),
                ['expires_at', '>', now()],
            ],
        );

        return $updated === 1;
    }

    public function revoke(int $userId, int $deviceId): bool
    {
        return $this->trustedDeviceRepository->deleteWhere([
            'id' => $deviceId,
            'user_id' => $userId,
        ]) === 1;
    }

    public function revokeAll(int $userId): void
    {
        $this->trustedDeviceRepository->deleteWhere(['user_id' => $userId]);
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
