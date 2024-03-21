<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Auth\AuthenticatedResponseResource;
use HiEvents\Services\Handlers\Auth\DTO\AuthenicatedResponseDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

abstract class BaseAuthAction extends BaseAction
{
    protected function respondWithToken(?string $token, Collection $accounts): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        return $this->jsonResponse(new AuthenticatedResponseResource(new AuthenicatedResponseDTO(
            token: $token,
            expiresIn: auth()->factory()->getTTL() * 60,
            accounts: $accounts,
            user: $user,
        )))
            ->cookie(
                Cookie::make(
                    name: 'token',
                    value: $token,
                    secure: true,
                )
            );
    }
}
