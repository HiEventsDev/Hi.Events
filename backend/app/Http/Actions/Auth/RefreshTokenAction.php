<?php

namespace HiEvents\Http\Actions\Auth;

use Illuminate\Http\JsonResponse;

class RefreshTokenAction extends BaseAuthAction
{
    public function __invoke(): JsonResponse
    {
        return $this->respondWithToken(auth()->refresh(), $this->getAuthenticatedUser()->accounts ?? collect());
    }
}
