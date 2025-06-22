<?php

namespace HiEvents\Services\Infrastructure\User;

use Illuminate\Cache\Repository;

class EmailVerificationCodeService
{
    public function __construct(
        private readonly Repository $cacheRepository,
    )
    {
    }

    public function storeAndReturnCode(string $email): int
    {
        $code = $this->generateNumericCode();

        $this->cacheRepository->put($this->getCacheKey($email), $code, now()->addMinutes(30));

        return $code;
    }

    public function verifyCode(string $email, string $code): bool
    {
        $cachedCode = $this->getCode($email);
        if ($cachedCode === null) {
            return false;
        }

        if ($cachedCode !== $code) {
            return false;
        }

        $this->cacheRepository->forget($this->getCacheKey($email));

        return true;
    }

    private function getCode(string $email): ?string
    {
        return $this->cacheRepository->get($this->getCacheKey($email));
    }

    private function generateNumericCode(): int
    {
        return random_int(10000, 99999);
    }

    private function getCacheKey(string $email): string
    {
        return 'email_verification_code:' . $email;
    }
}
