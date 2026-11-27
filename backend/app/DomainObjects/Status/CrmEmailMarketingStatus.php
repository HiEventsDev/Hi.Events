<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum CrmEmailMarketingStatus: string
{
    use BaseEnum;

    case NOT_SUBSCRIBED = 'NOT_SUBSCRIBED';
    case SUBSCRIBED = 'SUBSCRIBED';
    case UNSUBSCRIBED = 'UNSUBSCRIBED';
    case BOUNCED = 'BOUNCED';
    case COMPLAINED = 'COMPLAINED';

    public function suppressionStrength(): int
    {
        return match ($this) {
            self::COMPLAINED => 3,
            self::UNSUBSCRIBED => 2,
            self::BOUNCED => 1,
            default => 0,
        };
    }
}
