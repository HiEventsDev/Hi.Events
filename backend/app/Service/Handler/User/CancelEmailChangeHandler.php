<?php

namespace TicketKitten\Service\Handler\User;

use Psr\Log\LoggerInterface;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;

class CancelEmailChangeHandler
{
    private LoggerInterface $logger;

    private UserRepositoryInterface $userRepository;

    public function __construct(
        LoggerInterface         $logger,
        UserRepositoryInterface $userRepository,
    )
    {
        $this->logger = $logger;
        $this->userRepository = $userRepository;
    }

    public function handle(int $userId): UserDomainObject
    {
        $this->userRepository->updateWhere(
            attributes: [
                'pending_email' => null,
            ],
            where: [
                'id' => $userId,
            ]
        );

        $this->logger->info('Cancelled email change', [
            'user_id' => $userId
        ]);

        return $this->userRepository->findById($userId);
    }
}
