<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\Http\ResponseCodes;
use Illuminate\Http\JsonResponse;

class RefreshTokenAction extends BaseAuthAction
{
    public function __invoke(): JsonResponse
    {
        if (auth()->payload()->get('sso_connection_id') !== null) {
            return $this->jsonResponse([
                'message' => __('Your single sign-on session has ended. Please sign in again.'),
                'error_code' => SsoErrorCode::SESSION_EXPIRED->value,
            ], ResponseCodes::HTTP_UNAUTHORIZED);
        }

        return $this->respondWithToken(auth()->refresh(), $this->getAuthenticatedUser()->accounts ?? collect());
    }
}
