<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\TooManyPinAttemptsException;
use Illuminate\Cache\RateLimiter;

class BoxOfficePinAttemptLimiter
{
    private const MAX_FAILURES_PER_IP = 5;

    private const MAX_FAILURES_PER_BOX_OFFICE = 20;

    private const LOCKOUT_SECONDS = 900;

    public function __construct(
        private readonly RateLimiter $rateLimiter,
    ) {}

    /**
     * @throws TooManyPinAttemptsException
     */
    public function reserveAttempt(BoxOfficeDomainObject $boxOffice, string $ipAddress): void
    {
        $ipKey = $this->ipKey($boxOffice, $ipAddress);
        $boxOfficeKey = $this->boxOfficeKey($boxOffice);

        if ($this->rateLimiter->hit($ipKey, self::LOCKOUT_SECONDS) > self::MAX_FAILURES_PER_IP) {
            throw $this->lockedFor($this->rateLimiter->availableIn($ipKey));
        }

        if ($this->rateLimiter->hit($boxOfficeKey, self::LOCKOUT_SECONDS) > self::MAX_FAILURES_PER_BOX_OFFICE) {
            throw $this->lockedFor($this->rateLimiter->availableIn($boxOfficeKey));
        }
    }

    public function recordSuccess(BoxOfficeDomainObject $boxOffice, string $ipAddress): void
    {
        $this->rateLimiter->clear($this->ipKey($boxOffice, $ipAddress));
        $this->rateLimiter->decrement($this->boxOfficeKey($boxOffice), self::LOCKOUT_SECONDS);
    }

    private function lockedFor(int $seconds): TooManyPinAttemptsException
    {
        return new TooManyPinAttemptsException(__('Too many incorrect PINs. Try again in :minutes minutes, or ask an organizer to reset the PIN.', [
            'minutes' => max(1, (int) ceil($seconds / 60)),
        ]));
    }

    private function ipKey(BoxOfficeDomainObject $boxOffice, string $ipAddress): string
    {
        return sprintf('%s:%s', $this->boxOfficeKey($boxOffice), $ipAddress);
    }

    private function boxOfficeKey(BoxOfficeDomainObject $boxOffice): string
    {
        return sprintf('box_office_pin:%d:%s', $boxOffice->getId(), substr(hash('sha256', (string) $boxOffice->getPinHash()), 0, 16));
    }
}
