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
            Log::critical('Refusing requests: JWT_SECRET still holds the public example value from .env.example. On a manual install, run `php artisan jwt:secret --force` and restart. On Docker, set a new JWT_SECRET (for example from `openssl rand -base64 32`) in the environment or .env file your compose file reads, then run `docker compose up -d`.');

            return response()->noContent(ResponseCodes::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}
