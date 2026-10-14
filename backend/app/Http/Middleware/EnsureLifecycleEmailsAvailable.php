<?php

declare(strict_types=1);

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailAvailability;
use Illuminate\Http\Request;

class EnsureLifecycleEmailsAvailable
{
    public function __construct(
        private readonly LifecycleEmailAvailability $availability,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->availability->isAvailable()) {
            return response()->noContent(ResponseCodes::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
