<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Request\Auth\LoginRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Handlers\Auth\DTO\LoginCredentialsDTO;
use HiEvents\Services\Handlers\Auth\LoginHandler;
use Illuminate\Http\JsonResponse;

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
                accountId: $request->validated('account_id'),
            ));
        } catch (UnauthorizedException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        return $this->respondWithToken($loginResponse->token, $loginResponse->accounts);
    }
}
