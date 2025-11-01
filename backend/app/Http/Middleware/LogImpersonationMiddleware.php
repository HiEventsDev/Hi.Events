<?php

namespace HiEvents\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

class LogImpersonationMiddleware
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AuthManager     $authManager,
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $mutateMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        $isImpersonating = false;
        try {
            $isImpersonating = (bool)$this->authManager->payload()->get('is_impersonating', false);
        } catch (Exception) {
            // Not authenticated or no JWT token
        }

        if ($this->authManager->check()
            && $isImpersonating
            && in_array($request->method(), $mutateMethods, true)
        ) {
            $this->logger->info('Impersonation action by user ID ' . $this->authManager->payload()->get('impersonator_id'), [
                'impersonator_id' => $this->authManager->payload()->get('impersonator_id'),
                'impersonated_user_id' => $this->authManager->user()->id,
                'account_id' => $this->authManager->payload()->get('account_id'),
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
