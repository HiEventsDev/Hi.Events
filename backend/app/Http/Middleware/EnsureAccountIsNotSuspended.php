<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Exceptions\AccountSuspendedException;
use HiEvents\Services\Domain\Account\AccountSuspensionService;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsNotSuspended
{
    public function __construct(
        private readonly AuthUserService $authUserService,
        private readonly AccountSuspensionService $accountSuspensionService,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check() || $this->authUserService->isImpersonating() || $this->isAuthPath($request)) {
            return $next($request);
        }

        $accountId = $this->authUserService->getAuthenticatedAccountId();

        if ($accountId === null || ! $this->accountSuspensionService->isAccountSuspended($accountId)) {
            return $next($request);
        }

        if (! $this->routeRequiresAuthentication($request)) {
            $this->authUserService->treatRequestAsAnonymous($request);

            return $next($request);
        }

        return response()->json([
            'message' => __('This account has been suspended.'),
            'error_code' => AccountSuspendedException::ERROR_CODE,
        ], Response::HTTP_FORBIDDEN);
    }

    private function routeRequiresAuthentication(Request $request): bool
    {
        return collect($request->route()?->gatherMiddleware() ?? [])
            ->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'auth:'));
    }

    private function isAuthPath(Request $request): bool
    {
        return str_starts_with(trim($request->path(), '/'), 'auth/');
    }
}
