<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Helper\AuthCookieSameSite;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Auth\AuthenticatedResponseResource;
use HiEvents\Services\Application\Handlers\Auth\DTO\AuthenticatedResponseDTO;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

abstract class BaseAuthAction extends BaseAction
{
    protected function getAuthCookie(string $token): SymfonyCookie
    {
        return Cookie::make(
            name: 'token',
            value: $token,
            secure: true,
            sameSite: AuthCookieSameSite::forRequest(request()),
        );
    }

    protected function addTokenToResponse(JsonResponse|Response $response, ?string $token): JsonResponse
    {
        if (! $token) {
            return $response;
        }

        $response = $response->withCookie($this->getAuthCookie($token));

        $response->header('X-Auth-Token', $token);

        return $response;
    }

    protected function addTrustedDeviceCookie(JsonResponse $response, ?string $trustedDeviceToken): void
    {
        if (! $trustedDeviceToken) {
            return;
        }

        $response->withCookie(Cookie::make(
            name: TrustedDeviceService::COOKIE_NAME,
            value: $trustedDeviceToken,
            minutes: TrustedDeviceService::TRUST_DAYS * 24 * 60,
            secure: true,
            httpOnly: true,
            sameSite: AuthCookieSameSite::forRequest(request()),
        ));
    }

    protected function issueFreshTokenForCurrentSession(): string
    {
        $payload = auth()->payload();
        $guard = auth();
        $defaultTtl = $guard->factory()->getTTL();

        if ($payload->get('sso_connection_id') !== null) {
            $guard->setTTL(max(1, (int) ceil(((int) $payload->get('exp') - now()->getTimestamp()) / 60)));
        }

        try {
            return $guard->claims(array_filter([
                'account_id' => $payload->get('account_id'),
                'role' => $payload->get('role'),
                'auth_method' => $payload->get('auth_method'),
                'sso_connection_id' => $payload->get('sso_connection_id'),
            ], static fn ($value) => $value !== null))->tokenById(auth()->id());
        } finally {
            $guard->setTTL($defaultTtl);
        }
    }

    protected function respondWithToken(
        ?string $token,
        Collection $accounts,
        ?UserDomainObject $user = null,
        ?int $recoveryCodesRemaining = null,
    ): JsonResponse {
        $user ??= $this->getAuthenticatedUser();

        return $this->addTokenToResponse(
            response: $this->jsonResponse(new AuthenticatedResponseResource(new AuthenticatedResponseDTO(
                token: $token,
                expiresIn: auth()->factory()->getTTL() * 60,
                accounts: $accounts,
                user: $user,
                recoveryCodesRemaining: $recoveryCodesRemaining,
            ))),
            token: $token
        );
    }
}
