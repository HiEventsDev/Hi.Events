<?php

namespace HiEvents\Helper;

use Illuminate\Support\Str;

class IdHelper
{
    public const ATTENDEE_PREFIX = 'a';
    public const ORDER_PREFIX = 'o';
    public const EVENT_PREFIX = 'e';
    public const ACCOUNT_PREFIX = 'acc';

    public const SESSION_COOKIE_PREFIX = 'ord';

    public static function randomPrefixedId(string $prefix, int $length = 13): string
    {
        return sprintf('%s%s', $prefix, Str::random($length));
    }
}
