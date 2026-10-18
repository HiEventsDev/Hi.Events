<?php

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Auth\AuthUserService;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Payload;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenNotRevoked
{
    public const ERROR_CODE = 'SESSION_REVOKED';

    public function __construct(
        private readonly UserSessionRevocationService $revocationService,
        private readonly SsoEnforcementService $ssoEnforcementService,
        private readonly AuthUserService $authUserService,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $errorCode = $this->carriesToken($request) && Auth::check() && ! $this->authUserService->isApiKeyRequest()
            ? $this->rejectionCode()
            : null;

        if ($errorCode === null) {
            return $next($request);
        }

        if ($this->routeRequiresAuthentication($request)) {
            return response()->json([
                'message' => $errorCode === self::ERROR_CODE
                    ? __('Your session has ended. Please sign in again.')
                    : __('Your organization requires you to sign in with single sign-on.'),
                'error_code' => $errorCode,
            ], Response::HTTP_UNAUTHORIZED);
        }

        $this->treatAsAnonymous($request);

        return $next($request);
    }

    private function rejectionCode(): ?string
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $payload = Auth::payload();
        } catch (JWTException) {
            return null;
        }

        $accountId = $payload->get('account_id');
        $ssoConnectionId = $payload->get('sso_connection_id') === null ? null : (int) $payload->get('sso_connection_id');

        if ($accountId !== null && ! $this->ssoEnforcementService->isTokenAllowed(
            userId: $user->id,
            email: $user->email,
            accountId: (int) $accountId,
            authMethod: $payload->get('auth_method'),
            ssoConnectionId: $ssoConnectionId,
        )) {
            return SsoErrorCode::SSO_REQUIRED->value;
        }

        if ($this->isRevoked($user, $payload)
            || ($accountId !== null && ! $this->ssoEnforcementService->isConnectionSessionUsable((int) $accountId, $ssoConnectionId))) {
            return self::ERROR_CODE;
        }

        return null;
    }

    private function isRevoked(User $user, Payload $payload): bool
    {
        $issuedAt = (int) $payload->get('iat');
        $impersonatorId = $payload->get('is_impersonating') ? (int) $payload->get('impersonator_id') : null;

        return $this->revocationService->isRevoked($user->tokens_valid_after, $issuedAt)
            || ($impersonatorId !== null && $this->revocationService->isUserRevoked($impersonatorId, $issuedAt));
    }

    private function carriesToken(Request $request): bool
    {
        return $request->bearerToken() !== null || $request->cookies->has('token');
    }

    private function treatAsAnonymous(Request $request): void
    {
        $request->headers->remove('Authorization');
        $request->server->remove('HTTP_AUTHORIZATION');
        $request->server->remove('REDIRECT_HTTP_AUTHORIZATION');
        $request->cookies->remove('token');
        app('tymon.jwt')->unsetToken();
        Auth::guard('api')->forgetUser();
    }

    private function routeRequiresAuthentication(Request $request): bool
    {
        return collect($request->route()?->gatherMiddleware() ?? [])
            ->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'auth:'));
    }
}
