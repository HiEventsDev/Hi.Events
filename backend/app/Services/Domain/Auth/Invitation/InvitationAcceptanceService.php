<?php

namespace HiEvents\Services\Domain\Auth\Invitation;

use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\UserIdentityService;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Contracts\Hashing\Hasher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class InvitationAcceptanceService
{
    public function __construct(
        private readonly EncryptedPayloadService $encryptedPayloadService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly UserIdentityService $userIdentityService,
        private readonly TrustedDeviceService $trustedDeviceService,
        private readonly UserSessionRevocationService $sessionRevocationService,
        private readonly Hasher $hasher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws DecryptionFailedException
     * @throws EncryptedPayloadExpiredException
     * @throws ResourceConflictException
     */
    public function findPending(string $invitationToken): PendingInvitation
    {
        ['user_id' => $userId, 'account_id' => $accountId] = $this->encryptedPayloadService->decryptPayload($invitationToken);

        try {
            $user = $this->userRepository->findByIdAndAccountId($userId, $accountId);
        } catch (ResourceNotFoundException) {
            throw new ResourceNotFoundException(__('The invitation does not exist'));
        }

        if ($user->getCurrentAccountUser()?->getStatus() !== UserStatus::INVITED->name) {
            throw new ResourceConflictException(__('The invitation has already been accepted'));
        }

        return new PendingInvitation(
            user: $user,
            accountId: $accountId,
            requiresSignIn: $this->canAlreadySignIn($user),
            accountName: $this->accountRepository->findById($accountId)->getName(),
        );
    }

    private function canAlreadySignIn(UserDomainObject $user): bool
    {
        $hasActiveMembership = $this->accountUserRepository->findFirstWhere([
            'user_id' => $user->getId(),
            'status' => UserStatus::ACTIVE->name,
        ]) !== null;

        return $hasActiveMembership && ($user->getPassword() !== null || $this->userIdentityService->hasIdentity($user->getId()));
    }

    public function setUpNewUser(
        PendingInvitation $invitation,
        string $firstName,
        ?string $lastName,
        ?string $password,
        string $timezone,
        bool $marketingOptIn,
    ): void {
        $userId = $invitation->user->getId();

        $this->userIdentityService->deleteForUser($userId);
        $this->trustedDeviceService->revokeAll($userId);
        $this->sessionRevocationService->revokeAll($userId);

        $this->userRepository->updateWhere(
            attributes: [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => $password !== null ? $this->hasher->make($password) : null,
                'timezone' => $timezone,
                'email_verified_at' => now(),
                'marketing_opted_in_at' => $marketingOptIn ? now()->toDateTimeString() : null,
            ],
            where: ['id' => $userId],
        );

        $this->activate($invitation);
    }

    public function activate(PendingInvitation $invitation): void
    {
        $this->accountUserRepository->updateWhere(
            attributes: ['status' => UserStatus::ACTIVE->name],
            where: [
                'user_id' => $invitation->user->getId(),
                'account_id' => $invitation->accountId,
            ],
        );

        $this->logger->info('User accepted invitation', [
            'user_id' => $invitation->user->getId(),
            'account_id' => $invitation->accountId,
        ]);
    }
}
