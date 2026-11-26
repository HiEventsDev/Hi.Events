<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\SignupForm;

use Illuminate\Cache\RateLimiter;

class SignupSubmissionLimiter
{
    public const MAX_PER_ORGANIZER_PER_DAY = 3;

    public const MAX_ORGANIZERS_PER_ADDRESS_PER_DAY = 5;

    public const MAX_PER_FORM_PER_HOUR = 1000;

    private const DAY_IN_SECONDS = 86400;

    public function __construct(
        private readonly RateLimiter $rateLimiter,
    ) {}

    public function attempt(int $organizerId, int $formId, string $email): bool
    {
        $address = hash('sha256', $email);
        $organizerKey = 'crm-signup:'.$organizerId.':'.$address;
        $organizersKey = 'crm-signup-organizers:'.$address;
        $formKey = 'crm-signup-form:'.$formId;

        $isFirstFromOrganizer = ! $this->rateLimiter->tooManyAttempts($organizerKey, 1);

        if ($this->rateLimiter->tooManyAttempts($organizerKey, self::MAX_PER_ORGANIZER_PER_DAY)
            || ($isFirstFromOrganizer && $this->rateLimiter->tooManyAttempts($organizersKey, self::MAX_ORGANIZERS_PER_ADDRESS_PER_DAY))
            || $this->rateLimiter->tooManyAttempts($formKey, self::MAX_PER_FORM_PER_HOUR)) {
            return false;
        }

        $this->rateLimiter->hit($organizerKey, self::DAY_IN_SECONDS);
        $this->rateLimiter->hit($formKey, 3600);

        if ($isFirstFromOrganizer) {
            $this->rateLimiter->hit($organizersKey, self::DAY_IN_SECONDS);
        }

        return true;
    }
}
