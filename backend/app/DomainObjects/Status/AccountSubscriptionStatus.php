<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum AccountSubscriptionStatus: string
{
    use BaseEnum;

    case INCOMPLETE = 'INCOMPLETE';
    case INCOMPLETE_EXPIRED = 'INCOMPLETE_EXPIRED';
    case TRIALING = 'TRIALING';
    case ACTIVE = 'ACTIVE';
    case PAST_DUE = 'PAST_DUE';
    case CANCELED = 'CANCELED';
    case UNPAID = 'UNPAID';
    case PAUSED = 'PAUSED';

    public static function fromStripeStatus(string $stripeStatus): ?self
    {
        return self::tryFrom(strtoupper($stripeStatus));
    }

    public function grantsAccess(): bool
    {
        return in_array($this, [self::ACTIVE, self::TRIALING, self::PAST_DUE], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::CANCELED, self::INCOMPLETE_EXPIRED], true);
    }
}
