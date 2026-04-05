<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Request\Auth\LoginRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\Auth\DTO\LoginCredentialsDTO;
use HiEvents\Services\Application\Handlers\Auth\LoginHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LoginAction extends BaseAuthAction
{
    private LoginHandler $loginHandler;

    public function __construct(LoginHandler $loginHandler)
    {
        $this->loginHandler = $loginHandler;
    }

    public function __invoke(LoginRequest $request): JsonResponse
    {
        try {
            $loginResponse = $this->loginHandler->handle(new LoginCredentialsDTO(
                email: strtolower($request->validated('email')),
                password: $request->validated('password'),
                accountId: (int)$request->validated('account_id'),
            ));
        } catch (UnauthorizedException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        // Check if user has MFA enabled - issue challenge instead of token
        $user = User::where('email', strtolower($request->validated('email')))->first();

        if ($user && $user->mfa_enabled) {
            $mfaToken = Str::uuid()->toString();
            Cache::put("mfa_pending_{$mfaToken}", $user->id, 300); // 5 min TTL

            return $this->jsonResponse([
                'mfa_required' => true,
                'mfa_token' => $mfaToken,
            ], ResponseCodes::HTTP_OK);
        }

        return $this->respondWithToken($loginResponse->token, $loginResponse->accounts);
    }
}
