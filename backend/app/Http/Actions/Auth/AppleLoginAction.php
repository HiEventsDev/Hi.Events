<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Domain\Auth\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppleLoginAction extends BaseAuthAction
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
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $loginResponse = $this->oAuthService->authenticateWithApple(
                idToken: $request->input('id_token'),
                accountId: $request->input('account_id') ? (int)$request->input('account_id') : null,
                firstName: $request->input('first_name'),
                lastName: $request->input('last_name'),
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
