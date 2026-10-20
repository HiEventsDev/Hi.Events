<?php

namespace HiEvents\Services\Application\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\DTO\ConfirmEmailChangeDTO;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class ConfirmEmailChangeHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private UserRepositoryInterface $userRepository,
        private EncryptedPayloadService $encryptedPayloadService,
        private SsoEnforcementService $ssoEnforcementService,
        private DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws DecryptionFailedException|Throwable
     */
    public function handle(ConfirmEmailChangeDTO $data): UserDomainObject
    {
        return $this->databaseManager->transaction(function () use ($data) {
            $payload = $this->encryptedPayloadService->decryptPayload($data->token);
            $userId = $payload['id'];

            $user = $this->userRepository->findByIdAndAccountId($userId, $data->accountId);

            if ($user->getPendingEmail() === null || ($payload['email'] ?? null) !== $user->getPendingEmail()) {
                throw new ResourceConflictException(__('No email change pending'));
            }

            $userWithEmail = $this->userRepository->findFirstWhere([
                ['email', 'ilike', addcslashes($user->getPendingEmail(), '\\%_')],
                ['id', '!=', $userId],
            ]);

            if ($userWithEmail !== null) {
                throw new ResourceConflictException(__('This email address is already in use.'));
            }

            try {
                $this->ssoEnforcementService->assertEmailChangeAllowed($userId, $user->getPendingEmail());
            } catch (AccountSsoConfigurationException $exception) {
                throw new ResourceConflictException($exception->getMessage());
            }

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

            return $this->userRepository->findByIdAndAccountId($userId, $data->accountId);
        });
    }
}
