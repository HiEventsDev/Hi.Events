<?php

namespace HiEvents\Services\Handlers\Auth;

use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Handlers\Auth\DTO\AcceptInvitationDTO;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

readonly class AcceptInvitationHandler
{
    public function __construct(
        private EncryptedPayloadService        $encryptedPayloadService,
        private UserRepositoryInterface        $userRepository,
        private AccountUserRepositoryInterface $accountUserRepository,
        private Hasher                         $hasher,
        private DatabaseManager                $databaseManager,
    )
    {
    }

    /**
     * @throws DecryptionFailedException
     * @throws ResourceConflictException|EncryptedPayloadExpiredException|Throwable
     */
    public function handle(AcceptInvitationDTO $invitationData): void
    {
        $this->databaseManager->transaction(function () use ($invitationData) {
            $this->acceptInvitation($invitationData);
        });
    }

    /**
     * @throws EncryptedPayloadExpiredException
     * @throws DecryptionFailedException
     * @throws ResourceConflictException
     */
    private function acceptInvitation(AcceptInvitationDTO $invitationData): void
    {
        ['user_id' => $userId, 'account_id' => $accountId] = $this->encryptedPayloadService->decryptPayload($invitationData->invitation_token);

        $user = $this->userRepository->findByIdAndAccountId($userId, $accountId);

        if ($user === null) {
            throw new ResourceNotFoundException(__('The invitation does not exist'));
        }

        if ($user->getCurrentAccountUser()?->getStatus() !== UserStatus::INVITED->name) {
            throw new ResourceConflictException(__('The invitation has already been accepted'));
        }

        $this->userRepository->updateWhere(
            attributes: [
                'first_name' => $invitationData->first_name,
                'last_name' => $invitationData->last_name,
                'password' => $this->hasher->make($invitationData->password),
                'timezone' => $invitationData->timezone,
                'email_verified_at' => now(),
            ],
            where: [
                'id' => $userId,
            ]
        );

        $this->accountUserRepository->updateWhere(
            attributes: [
                'status' => UserStatus::ACTIVE->name,
            ],
            where: [
                'user_id' => $userId,
                'account_id' => $accountId,
            ]
        );
    }
}
