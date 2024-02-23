<?php

namespace TicketKitten\Http\Actions\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cookie;
use TicketKitten\Http\Actions\BaseAction;

abstract class BaseAuthAction extends BaseAction
{
    protected function respondWithToken(string $token): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        return $this
            ->jsonResponse(
                [
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth()->factory()->getTTL() * 60,
                    'user' => [
                        'id' => $user->getId(),
                        'account_id' => $user->getAccountId(),
                        'first_name' => $user->getFirstName(),
                        'last_name' => $user->getLastName(),
                        'email' => $user->getEmail()
                    ]
                ]
            )->cookie(
                Cookie::make(
                    name: 'token',
                    value: $token,
                    secure: true,
                )
            );
    }
}
