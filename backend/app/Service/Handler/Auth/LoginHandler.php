<?php

namespace TicketKitten\Service\Handler\Auth;

use TicketKitten\Http\DataTransferObjects\LoginCredentialsDTO;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;
use TicketKitten\Service\Common\Auth\DTO\LoginResponse;
use TicketKitten\Service\Common\Auth\LoginService;

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
