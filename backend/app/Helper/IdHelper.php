<?php

namespace HiEvents\Helper;

use Illuminate\Support\Str;

class IdHelper
{
    public const ATTENDEE_PREFIX = 'a';
    public const ORDER_PREFIX = 'o';
    public const EVENT_PREFIX = 'e';
    public const ACCOUNT_PREFIX = 'acc';

    public const CHECK_IN_LIST_PREFIX = 'cil';
    public const CHECK_IN_PREFIX = 'ci';

    public static function shortId(string $prefix, int $length = 13): string
    {
        return sprintf('%s_%s', $prefix, Str::random($length));
    }

    public static function publicId(string $prefix = '', string $suffix = '', int $length = 7): string
    {
        return Str::upper($prefix . '-' . Str::random($length) . $suffix);
    }
}
