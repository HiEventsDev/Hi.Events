<?php

namespace HiEvents\Services\Domain\Auth\TwoFactor;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\TwoFactorLockedOutException;
use Illuminate\Cache\RateLimiter;

class TwoFactorVerifier
{
    public const MAX_FAILED_ATTEMPTS = 10;

    public const LOCKOUT_SECONDS = 900;

    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly RateLimiter $rateLimiter,
    ) {}

    /**
     * @throws TwoFactorLockedOutException
     */
    public function verify(UserDomainObject $user, ?string $code, ?string $recoveryCode = null): bool
    {
        if (blank($code) && blank($recoveryCode)) {
            return false;
        }

        $key = 'two_factor_failures:'.$user->getId();

        if ($this->rateLimiter->tooManyAttempts($key, self::MAX_FAILED_ATTEMPTS)) {
            throw new TwoFactorLockedOutException(__('Too many incorrect codes. Wait 15 minutes and try again.'));
        }

        $verified = $recoveryCode !== null
            ? $this->recoveryCodeService->consume($user, $recoveryCode)
            : $this->twoFactorAuthenticationService->verifyCode($user, (string) $code);

        if ($verified) {
            $this->rateLimiter->clear($key);
        } else {
            $this->rateLimiter->hit($key, self::LOCKOUT_SECONDS);
        }

        return $verified;
    }
}
