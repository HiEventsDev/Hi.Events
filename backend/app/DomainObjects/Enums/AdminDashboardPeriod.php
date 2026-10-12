<?php

namespace HiEvents\DomainObjects\Enums;

use Carbon\CarbonImmutable;

enum AdminDashboardPeriod: string
{
    use BaseEnum;

    case LAST_24_HOURS = '24h';
    case LAST_7_DAYS = '7d';
    case LAST_30_DAYS = '30d';

    public function since(CarbonImmutable $now): CarbonImmutable
    {
        return match ($this) {
            self::LAST_24_HOURS => $now->subDay(),
            self::LAST_7_DAYS => $now->subDays(7),
            self::LAST_30_DAYS => $now->subDays(30),
        };
    }
}
