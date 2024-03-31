<?php

namespace HiEvents\Helper;

use Carbon\Carbon;

class DateHelper
{
    public static function convertToUTC(string $eventDate, string $userTimezone): string
    {
        return Carbon::parse($eventDate, $userTimezone)
            ->setTimezone('UTC')
            ->toString();
    }

    public static function convertFromUTC(string $eventDate, string $userTimezone): string
    {
        return Carbon::parse($eventDate, 'UTC')
            ->setTimezone($userTimezone)
            ->toString();
    }
}
