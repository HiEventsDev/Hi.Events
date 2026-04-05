<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use Illuminate\Support\Facades\Hash;

readonly class PrivateEventAccessService
{
    /**
     * Validate access to a private event.
     *
     * @throws UnauthorizedException
     */
    public function validateAccess(EventSettingDomainObject $settings, ?string $accessCode): bool
    {
        if (!$settings->getIsPrivateEvent()) {
            return true;
        }

        $storedCode = $settings->getPrivateAccessCode();

        if ($storedCode === null) {
            return true;
        }

        if ($accessCode === null) {
            throw new UnauthorizedException(__('This is a private event. An access code is required.'));
        }

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($storedCode, $accessCode)) {
            throw new UnauthorizedException(__('Invalid access code'));
        }

        return true;
    }

    /**
     * Filter event details based on privacy settings.
     */
    public function filterEventData(array $eventData, EventSettingDomainObject $settings, bool $hasAccess): array
    {
        if (!$settings->getIsPrivateEvent()) {
            return $eventData;
        }

        if ($settings->getHideEventDetailsUntilAccess() && !$hasAccess) {
            // Strip sensitive fields - only show title, date, and basic info
            $allowedFields = ['id', 'title', 'start_date', 'end_date', 'status', 'currency', 'timezone', 'short_id'];
            return array_intersect_key($eventData, array_flip($allowedFields));
        }

        return $eventData;
    }

    /**
     * Determine if location should be hidden.
     */
    public function shouldHideLocation(EventSettingDomainObject $settings, bool $hasPurchased): bool
    {
        return $settings->getHideLocationUntilPurchase() && !$hasPurchased;
    }
}
