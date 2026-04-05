<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Domain\Auth\LoginService;
use HiEvents\Services\Domain\Auth\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebAuthnLoginAction extends BaseAuthAction
{
    public function __construct(
        private readonly WebAuthnService $webAuthnService,
        private readonly LoginService    $loginService,
    )
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => ['required', 'string'],
            'assertion' => ['required', 'array'],
            'assertion.id' => ['required', 'string'],
            'assertion.response.authenticatorData' => ['required', 'string'],
            'assertion.response.clientDataJSON' => ['required', 'string'],
            'assertion.response.signature' => ['required', 'string'],
            'account_id' => ['nullable', 'integer'],
        ]);

        try {
            $user = $this->webAuthnService->verifyAuthentication(
                sessionId: $request->input('session_id'),
                assertion: $request->input('assertion'),
                request: $request,
            );

            $loginResponse = $this->loginService->authenticateOAuthUser(
                $user,
                $request->input('account_id') ? (int)$request->input('account_id') : null,
            );

            return $this->respondWithToken($loginResponse->token, $loginResponse->accounts);
        } catch (\RuntimeException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }
    }
}
