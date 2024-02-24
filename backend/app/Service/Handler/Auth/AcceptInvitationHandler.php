<?php

namespace HiEvents\Service\Handler\Auth;

use Illuminate\Contracts\Hashing\Hasher;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Exceptions\DecryptionFailedException;
use HiEvents\Exceptions\EncryptedPayloadExpiredException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\DataTransferObjects\AcceptInvitationDTO;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Service\Common\EncryptedPayloadService;

readonly class AcceptInvitationHandler
{
    public function __construct(
        private EncryptedPayloadService $encryptedPayloadService,
        private UserRepositoryInterface $userRepository,
        private Hasher                  $hasher,
    )
    {
    }

    /**
     * @throws DecryptionFailedException
     * @throws ResourceConflictException|EncryptedPayloadExpiredException
     */
    public function handle(AcceptInvitationDTO $invitationData): void
    {
        ['user_id' => $userId, 'email' => $email] = $this->encryptedPayloadService->decryptPayload($invitationData->invitation_token);

        $user = $this->userRepository->findFirstWhere([
            'id' => $userId,
            'email' => $email,
        ]);

        if ($user === null) {
            throw new ResourceNotFoundException(__('The invitation does not exist'));
        }

        if ($user->getStatus() !== UserStatus::INVITED->name) {
            throw new ResourceConflictException(__('The invitation has already been accepted'));
        }

        $this->userRepository->updateWhere(
            attributes: [
                'first_name' => $invitationData->first_name,
                'last_name' => $invitationData->last_name,
                'password' => $this->hasher->make($invitationData->password),
                'timezone' => $invitationData->timezone,
                'status' => UserStatus::ACTIVE->name,
                'email_verified_at' => now(),
            ],
            where: [
                'id' => $userId,
                'email' => $email,
            ]
        );
    }
}
