<?php

namespace HiEvents\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;

class ApplySsrClientIp
{
    public const KEY_HEADER = 'X-Hi-Ssr-Key';

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $key = $request->headers->get(self::KEY_HEADER);
        $request->headers->remove(self::KEY_HEADER);

        $visitorIp = trim(explode(',', (string) $request->headers->get('X-Forwarded-For'))[0]);

        if ($this->isTrustedSsrRequest($key) && filter_var($visitorIp, FILTER_VALIDATE_IP) !== false) {
            $request->headers->set('X-Forwarded-For', $visitorIp);
            Request::setTrustedProxies(
                array_values(array_unique([...Request::getTrustedProxies(), $request->server->get('REMOTE_ADDR')])),
                Request::getTrustedHeaderSet(),
            );
        }

        return $next($request);
    }

    private function isTrustedSsrRequest(?string $key): bool
    {
        $secret = (string) $this->config->get('app.ssr_shared_secret');

        return $secret !== '' && $key !== null && hash_equals($secret, $key);
    }
}
