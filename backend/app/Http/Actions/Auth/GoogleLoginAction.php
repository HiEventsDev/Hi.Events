<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Domain\Auth\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleLoginAction extends BaseAuthAction
{
    public function __construct(
        private readonly OAuthService $oAuthService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
            'account_id' => ['nullable', 'integer'],
        ]);

        try {
            $loginResponse = $this->oAuthService->authenticateWithGoogle(
                idToken: $request->input('id_token'),
                accountId: $request->input('account_id') ? (int)$request->input('account_id') : null,
            );
        } catch (UnauthorizedException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        return $this->respondWithToken($loginResponse->token, $loginResponse->accounts);
    }
}
