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

    private function generateNumericCode(int $length = 5): string
    {
        return str_pad(random_int(0, 99999), $length, '0', STR_PAD_LEFT);
    }

    private function getCacheKey(string $email): string
    {
        return 'email_verification_code:' . $email;
    }
}
