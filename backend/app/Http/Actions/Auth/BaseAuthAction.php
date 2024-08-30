<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Auth\AuthenticatedResponseResource;
use HiEvents\Services\Handlers\Auth\DTO\AuthenticatedResponseDTO;
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
            sameSite: 'None',
        );
    }

    protected function addTokenToResponse(JsonResponse|Response $response, ?string $token): JsonResponse
    {
        if (!$token) {
            return $response;
        }

        $response = $response->withCookie($this->getAuthCookie($token));

        $response->header('X-Auth-Token', $token);

        return $response;
    }

    protected function respondWithToken(?string $token, Collection $accounts): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        return $this->addTokenToResponse(
            response: $this->jsonResponse(new AuthenticatedResponseResource(new AuthenticatedResponseDTO(
                token: $token,
                expiresIn: auth()->factory()->getTTL() * 60,
                accounts: $accounts,
                user: $user,
            ))),
            token: $token
        );
    }
}
