<?php

namespace HiEvents\DomainObjects\Enums;

enum SubscriptionBillingInterval: string
{
    use BaseEnum;

    case MONTH = 'month';
    case YEAR = 'year';

    public function priceLookupKey(): string
    {
        return match ($this) {
            self::MONTH => 'hi_events_pro_monthly',
            self::YEAR => 'hi_events_pro_yearly',
        };
    }
}
