<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Identity;

final class PhoneMask
{
    private const VISIBLE_START = 3;

    private const VISIBLE_END = 3;

    public static function mask(string $phone): string
    {
        $hidden = max(strlen($phone) - self::VISIBLE_START - self::VISIBLE_END, 0);

        return substr($phone, 0, self::VISIBLE_START).str_repeat('•', $hidden).substr($phone, -self::VISIBLE_END);
    }
}
