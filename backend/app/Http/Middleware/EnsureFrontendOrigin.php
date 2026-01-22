<?php

declare(strict_types=1);

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFrontendOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        // If the request is authenticated via Authorization header (bearer token),
        // CSRF is not applicable because the browser cannot attach it cross-site.
        $authorization = (string)$request->headers->get('Authorization');
        $hasBearerToken = str_starts_with($authorization, 'Bearer ');

        // If there's no auth cookie, treat it as non-cookie auth.
        $hasAuthCookie = $request->cookies->has('token');

        if ($hasBearerToken || !$hasAuthCookie) {
            return $next($request);
        }

        // For cookie-authenticated state-changing requests, enforce same-origin.
        if (in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $allowedOrigin = rtrim((string)config('app.frontend_url'), '/');

            $origin = rtrim((string)$request->headers->get('Origin'), '/');
            $referer = (string)$request->headers->get('Referer');

            $originOk = $origin !== '' && $origin === $allowedOrigin;
            $refererOk = $referer !== '' && str_starts_with($referer, $allowedOrigin . '/');

            if (!$originOk && !$refererOk) {
                return response()->json([
                    'message' => __('Invalid request origin.'),
                ], ResponseCodes::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
