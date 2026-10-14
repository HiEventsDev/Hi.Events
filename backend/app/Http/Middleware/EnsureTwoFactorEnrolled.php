<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Auth\AuthUserService;
use HiEvents\Services\Domain\Auth\TwoFactor\TwoFactorRequirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnrolled
{
    public const ERROR_CODE = 'TWO_FACTOR_SETUP_REQUIRED';

    public function __construct(
        private readonly AuthUserService $authUserService,
        private readonly TwoFactorRequirementService $twoFactorRequirementService,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $this->routeRequiresAuthentication($request) || ! Auth::check()) {
            return $next($request);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->two_factor_confirmed_at !== null || $this->isImpersonating() || $this->isAllowedBeforeEnrolment($request)) {
            return $next($request);
        }

        $accountId = $this->authUserService->getAuthenticatedAccountId();

        if ($accountId === null || ! $this->twoFactorRequirementService->isRequiredByAccount($accountId)) {
            return $next($request);
        }

        return response()->json([
            'message' => __('This account requires two-factor authentication. Set it up to continue.'),
            'error_code' => self::ERROR_CODE,
        ], Response::HTTP_FORBIDDEN);
    }

    private function routeRequiresAuthentication(Request $request): bool
    {
        return collect($request->route()?->gatherMiddleware() ?? [])
            ->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'auth:'));
    }

    private function isImpersonating(): bool
    {
        try {
            return (bool) Auth::payload()->get('is_impersonating', false);
        } catch (JWTException) {
            return false;
        }
    }

    private function isAllowedBeforeEnrolment(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if (str_starts_with($path, 'auth/') || str_starts_with($path, 'users/me/two-factor')) {
            return true;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        return $path === 'users/me'
            || $path === 'accounts'
            || preg_match('#^accounts/\d+$#', $path) === 1;
    }
}
