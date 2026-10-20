<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Lineup;

use Carbon\CarbonImmutable;

class ScheduleDay
{
    public const BOUNDARY_HOUR = 5;

    public static function key(CarbonImmutable $local): string
    {
        return $local->hour < self::BOUNDARY_HOUR
            ? $local->subDay()->toDateString()
            : $local->toDateString();
    }

    public static function keyForUtc(string $utc, string $timezone): string
    {
        return self::key(CarbonImmutable::parse($utc, 'UTC')->setTimezone($timezone));
    }
}
