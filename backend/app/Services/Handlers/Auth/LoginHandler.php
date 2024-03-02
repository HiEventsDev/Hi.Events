<?php

namespace HiEvents\Services\Handlers\Auth;

use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Common\Auth\DTO\LoginResponse;
use HiEvents\Services\Common\Auth\LoginService;
use HiEvents\Services\Handlers\Auth\DTO\LoginCredentialsDTO;

class LoginHandler
{
    private UserRepositoryInterface $userRepository;

    private LoginService $loginService;

    public function __construct(LoginService $loginService, UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
        $this->loginService = $loginService;
    }

    public function handle(LoginCredentialsDTO $loginCredentials): LoginResponse
    {
        $loginResponse = $this->loginService->authenticate(
            email: $loginCredentials->email,
            password: $loginCredentials->password,
        );

        $this->userRepository->updateWhere(
            attributes: [
                'last_login_at' => now()
            ],
            where: [
                'id' => $loginResponse->user->getId(),
            ],
        );

        return $loginResponse;
    }
}
