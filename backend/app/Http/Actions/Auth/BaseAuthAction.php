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
