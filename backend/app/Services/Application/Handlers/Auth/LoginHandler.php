<?php

namespace HiEvents\Services\Application\Handlers\Auth;

use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Services\Application\Handlers\Auth\DTO\LoginCredentialsDTO;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use HiEvents\Services\Domain\Auth\LoginService;

readonly class LoginHandler
{
    public function __construct(
        private LoginService $loginService,
    ) {}

    /**
     * @throws UnauthorizedException
     */
    public function handle(LoginCredentialsDTO $loginCredentials): LoginResponse
    {
        return $this->loginService->authenticate(
            email: $loginCredentials->email,
            password: $loginCredentials->password,
            requestedAccountId: $loginCredentials->accountId,
            trustedDeviceToken: $loginCredentials->trustedDeviceToken,
        );
    }
}
