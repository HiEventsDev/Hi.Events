<?php

namespace HiEvents\Services\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Handlers\User\DTO\CancelEmailChangeDTO;
use Psr\Log\LoggerInterface;

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

    public function handle(CancelEmailChangeDTO $data): UserDomainObject
    {
        $this->userRepository->updateWhere(
            attributes: [
                'pending_email' => null,
            ],
            where: [
                'id' => $data->userId,
            ]
        );

        $this->logger->info('Cancelled email change', [
            'user_id' => $data->userId,
            'account_id' => $data->accountId,
        ]);

        return $this->userRepository->findByIdAndAccountId($data->userId, $data->accountId);
    }
}
