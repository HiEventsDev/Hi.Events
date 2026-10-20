<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsNotPendingDeletion
{
    public const ERROR_CODE = 'ACCOUNT_PENDING_DELETION';

    public function __construct(
        private readonly AuthUserService $authUserService,
        private readonly AccountDeletionService $accountDeletionService,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $accountId = $this->authUserService->getAuthenticatedAccountId();

        if ($accountId === null || $this->isAllowedWhilePendingDeletion($request)) {
            return $next($request);
        }

        if (! $this->accountDeletionService->isAccountPendingDeletion($accountId)) {
            return $next($request);
        }

        return response()->json([
            'message' => __('This account is scheduled for deletion. Cancel the deletion request to continue using it.'),
            'error_code' => self::ERROR_CODE,
        ], Response::HTTP_FORBIDDEN);
    }

    private function isAllowedWhilePendingDeletion(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if (str_starts_with($path, 'auth/') || str_starts_with($path, 'admin/')) {
            return true;
        }

        if ($path === 'accounts/deletion-request') {
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
