<?php

namespace HiEvents\Http\Middleware;

use HiEvents\Helper\CloudflareIpRanges;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * @return array<int, string>|string
     */
    protected function proxies(): array|string
    {
        $configured = trim((string) config('app.trusted_proxies', '*'));

        if ($configured === '' || $configured === '*' || $configured === '**') {
            return '*';
        }

        $proxies = [];
        foreach (explode(',', $configured) as $proxy) {
            $proxy = trim($proxy);

            if ($proxy === CloudflareIpRanges::KEYWORD) {
                array_push($proxies, ...CloudflareIpRanges::RANGES);
            } elseif ($proxy !== '') {
                $proxies[] = $proxy;
            }
        }

        return $proxies;
    }
}
