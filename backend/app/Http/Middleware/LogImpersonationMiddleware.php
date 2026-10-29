<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

class LogImpersonationMiddleware
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AuthManager $authManager,
        private readonly AuthUserService $authUserService,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $mutateMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if ($this->authUserService->isImpersonating()
            && in_array($request->method(), $mutateMethods, true)
        ) {
            $impersonatorId = $this->authUserService->getImpersonatorId();

            $this->logger->info('Impersonation action by user ID '.$impersonatorId, [
                'impersonator_id' => $impersonatorId,
                'impersonated_user_id' => $this->authManager->user()->id,
                'account_id' => $this->authUserService->getAuthenticatedAccountId(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'payload' => $request->except(['password', 'token', 'password_confirmation', 'image']),
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        return $next($request);
    }
}
