<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Auth\AuthUserService;

class SetAccountContext
{
    public function __construct(
        private readonly AuthUserService $authUserService,
    ) {}

    public function handle($request, Closure $next)
    {
        if ($accountId = $this->authUserService->getAuthenticatedAccountId()) {
            User::setCurrentAccountId($accountId);
        }

        return $next($request);
    }
}
