<?php

namespace HiEvents\Helper;

class EmailHelper
{
    private const PLUS_ALIASING_PROVIDERS = [
        'gmail.com',
        'googlemail.com',
        'outlook.com',
        'hotmail.com',
        'live.com',
        'protonmail.com',
        'proton.me',
        'fastmail.com',
        'yahoo.com',
        'icloud.com',
    ];

    public static function normalize(string $email): string
    {
        $email = strtolower(trim($email));
        [$local, $domain] = explode('@', $email, 2);

        if (in_array($domain, self::PLUS_ALIASING_PROVIDERS, true)) {
            $local = preg_replace('/\+.*$/', '', $local);
        }

        return $local . '@' . $domain;
    }
}
