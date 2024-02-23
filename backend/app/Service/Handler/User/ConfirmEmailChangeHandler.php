<?php

namespace TicketKitten\Service\Handler\User;

use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\UnauthorizedException;
use Psr\Log\LoggerInterface;
use Throwable;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Exceptions\DecryptionFailedException;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;
use TicketKitten\Service\Common\EncryptedPayloadService;

class ConfirmEmailChangeHandler
{
    private LoggerInterface $logger;

    private UserRepositoryInterface $userRepository;

    private EncryptedPayloadService $encryptedPayloadService;

    private DatabaseManager $databaseManager;

    public function __construct(
        LoggerInterface         $logger,
        UserRepositoryInterface $userRepository,
        EncryptedPayloadService $encryptedPayloadService,
        DatabaseManager         $databaseManager,
    )
    {
        $this->logger = $logger;
        $this->userRepository = $userRepository;
        $this->encryptedPayloadService = $encryptedPayloadService;
        $this->databaseManager = $databaseManager;
    }

    /**
     * @throws DecryptionFailedException|Throwable
     */
    public function handle(string $token, int $authUserId): UserDomainObject
    {
        return $this->databaseManager->transaction(function () use ($token, $authUserId) {
            ['id' => $userId] = $this->encryptedPayloadService->decryptPayload($token);

            if ($userId !== $authUserId) {
                throw new UnauthorizedException();
            }

            $user = $this->userRepository->findById($userId);

            $this->userRepository->updateWhere(
                attributes: [
                    'email' => $user->getPendingEmail(),
                    'pending_email' => null,
                ],
                where: [
                    'id' => $userId,
                ]
            );

            $this->logger->info('Confirming email change', [
                'user_id' => $userId,
                'old_email' => $user->getEmail(),
                'new_email' => $user->getPendingEmail(),
            ]);

            return $this->userRepository->findById($userId);
        });
    }
}
