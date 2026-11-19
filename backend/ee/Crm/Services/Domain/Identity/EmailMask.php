<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Identity;

final class EmailMask
{
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('•', 5).'@'.$domain;
    }
}
