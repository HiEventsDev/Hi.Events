<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Helper\CorsAllowedOrigins;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ValidateMcpOrigin
{
    public function handle(Request $request, Closure $next): mixed
    {
        $origin = $request->headers->get('Origin');

        if ($origin === null || $this->isAllowed($origin)) {
            return $next($request);
        }

        throw new AccessDeniedHttpException(__('This origin may not call the MCP server.'));
    }

    private function isAllowed(string $origin): bool
    {
        $allowedOrigins = (array) config('cors.allowed_origins');

        return in_array(CorsAllowedOrigins::WILDCARD, $allowedOrigins, true)
            || in_array($origin, $allowedOrigins, true);
    }
}
