<?php

declare(strict_types=1);

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RejectPublishedSecrets
{
    private const PUBLISHED_JWT_SECRET = '2hoccgHb9r1fqW1lU16C6khSHVa7O0eai6FxkWK95UtQ0LqNDTO5mq1RzDwcq18I';

    public function handle(Request $request, Closure $next): mixed
    {
        if (config('jwt.secret') === self::PUBLISHED_JWT_SECRET) {
            Log::critical('Refusing requests: JWT_SECRET still holds the public example value from .env.example. Generate a new one with `php artisan jwt:secret --force`.');

            return response()->noContent(ResponseCodes::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}
