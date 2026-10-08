<?php

namespace HiEvents\Helper;

use Illuminate\Http\Request;

final class AuthCookieSameSite
{
    public const LAX = 'Lax';

    public const NONE = 'None';

    public static function forRequest(Request $request): string
    {
        $fetchSite = $request->headers->get('Sec-Fetch-Site');

        if ($fetchSite !== null) {
            return strtolower($fetchSite) === 'cross-site' ? self::NONE : self::LAX;
        }

        $originHost = parse_url((string) $request->headers->get('Origin'), PHP_URL_HOST);

        if (! is_string($originHost)) {
            return self::LAX;
        }

        return strcasecmp($originHost, $request->getHost()) === 0 ? self::LAX : self::NONE;
    }
}
