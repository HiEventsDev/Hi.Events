<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Auth;

use Illuminate\Http\JsonResponse;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\DataTransferObjects\LoginCredentialsDTO;
use HiEvents\Http\Request\Auth\LoginRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Service\Handler\Auth\LoginHandler;

class LoginAction extends BaseAuthAction
{
    private LoginHandler $loginHandler;

    public function __construct(LoginHandler $loginHandler)
    {
        $this->loginHandler = $loginHandler;
    }

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $credentials = [
            'email' => strtolower($request->validated('email')),
            'password' => $request->validated('password'),
        ];

        try {
            $loginResponse = $this->loginHandler->handle(new LoginCredentialsDTO(
                email: $credentials['email'],
                password: $credentials['password'],
            ));
        } catch (UnauthorizedException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        return $this->respondWithToken($loginResponse->token);
    }
}
