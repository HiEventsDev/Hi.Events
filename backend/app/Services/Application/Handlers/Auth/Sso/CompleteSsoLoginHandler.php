<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoTicketType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\DTO\LoginResponse;
use HiEvents\Services\Domain\Auth\LoginService;
use HiEvents\Services\Domain\Auth\Sso\DTO\AuthContext;
use HiEvents\Services\Domain\Auth\Sso\SsoTicketService;

class CompleteSsoLoginHandler
{
    public function __construct(
        private readonly SsoTicketService $ticketService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoginService $loginService,
    ) {}

    /**
     * @throws SsoException
     * @throws UnauthorizedException
     */
    public function handle(string $loginTicket, string $binding, int $accountId): LoginResponse
    {
        $payload = $this->ticketService->find(SsoTicketType::LOGIN, $loginTicket, $binding);

        $loginResponse = $this->loginService->completeLogin(
            user: $this->userRepository->findById((int) $payload['user_id']),
            requestedAccountId: $accountId,
            context: AuthContext::from($payload['context']),
        );

        $this->ticketService->consume(SsoTicketType::LOGIN, $loginTicket, $binding);

        return $loginResponse;
    }
}
