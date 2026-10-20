<?php

namespace HiEvents\Enterprise\Sso\Services\Domain;

class FreeMailDomains
{
    private const DOMAINS = [
        '163.com', '126.com', 'aim.com', 'aol.com', 'att.net', 'bk.ru', 'btinternet.com', 'comcast.net', 'duck.com',
        'email.com', 'eircom.net', 'fastmail.com', 'fastmail.fm', 'free.fr', 'gmail.com', 'gmx.at', 'gmx.ch',
        'gmx.com', 'gmx.de', 'gmx.net', 'googlemail.com', 'hey.com', 'hotmail.co.uk', 'hotmail.com', 'hotmail.de',
        'hotmail.es', 'hotmail.fr', 'hotmail.it', 'hushmail.com', 'icloud.com', 'inbox.ru', 'laposte.net',
        'libero.it', 'list.ru', 'live.co.uk', 'live.com', 'live.de', 'live.fr', 'live.nl', 'mac.com', 'mail.com',
        'mail.ru', 'me.com', 'msn.com', 'naver.com', 'orange.fr', 'outlook.co.uk', 'outlook.com', 'outlook.de',
        'outlook.es', 'outlook.fr', 'pm.me', 'proton.me', 'protonmail.ch', 'protonmail.com', 'qq.com',
        'rambler.ru', 'rediffmail.com', 'rocketmail.com', 'sbcglobal.net', 'seznam.cz', 't-online.de',
        'tutanota.com', 'tuta.io', 'verizon.net', 'web.de', 'wp.pl', 'yahoo.ca', 'yahoo.co.in', 'yahoo.co.jp',
        'yahoo.co.uk', 'yahoo.com', 'yahoo.com.au', 'yahoo.com.br', 'yahoo.de', 'yahoo.es', 'yahoo.fr',
        'yahoo.it', 'yandex.com', 'yandex.ru', 'ymail.com', 'zoho.com', 'zohomail.com',
    ];

    public static function contains(string $domain): bool
    {
        return in_array(strtolower($domain), self::DOMAINS, true);
    }
}
