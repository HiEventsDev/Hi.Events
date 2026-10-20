<?php

namespace HiEvents\Services\Application\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Services\Domain\SsoEnforcementService;
use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Mail\User\ConfirmEmailChangeMail;
use HiEvents\Mail\User\EmailChangeRequestedMail;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\DTO\UpdateMeDTO;
use HiEvents\Services\Domain\Auth\IdentityConfirmationService;
use HiEvents\Services\Domain\Auth\TwoFactor\TrustedDeviceService;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Mail\Mailer;

readonly class UpdateMeHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private Hasher $hasher,
        private Mailer $mailer,
        private EncryptedPayloadService $encryptedPayloadService,
        private TrustedDeviceService $trustedDeviceService,
        private AccountUserRepositoryInterface $accountUserRepository,
        private IdentityConfirmationService $identityConfirmationService,
        private SsoEnforcementService $ssoEnforcementService,
    ) {}

    /**
     * @throws PasswordInvalidException
     * @throws ResourceConflictException
     * @throws IdentityConfirmationFailedException
     */
    public function handle(UpdateMeDTO $updateUserData): UserDomainObject
    {
        $existingUser = $this->getExistingUser($updateUserData);
        $updateArray = [];

        if ($this->isChangingPassword($updateUserData)) {
            $this->validateCurrentPassword($updateUserData, $existingUser);
            $updateArray['password'] = $this->hasher->make($updateUserData->password);
            $this->trustedDeviceService->revokeAll($existingUser->getId());
        }

        if ($this->isUpdatingDetails($updateUserData)) {
            $updateArray += [
                'first_name' => $updateUserData->first_name,
                'last_name' => $updateUserData->last_name,
                'timezone' => $updateUserData->timezone,
                'locale' => $updateUserData->locale,
            ];

            if ($this->isChangingEmail($updateUserData, $existingUser)) {
                $this->confirmIdentity($updateUserData, $existingUser);
                $this->assertEmailIsAvailable($updateUserData->email, $existingUser->getId());
                $this->assertEmailKeepsSsoDomain($existingUser->getId(), $updateUserData->email);
                $updateArray['pending_email'] = $updateUserData->email;
                $this->sendEmailChangeConfirmation($existingUser, $updateUserData->email);
                $this->notifyCurrentAddressOfEmailChange($existingUser);
            }
        }

        if ($updateUserData->marketing_opt_in !== null) {
            $updateArray['marketing_opted_in_at'] = $updateUserData->marketing_opt_in
                ? now()->toDateTimeString()
                : null;
        }

        $this->userRepository->updateWhere(
            attributes: $updateArray,
            where: [
                'id' => $updateUserData->id,
            ]
        );

        if ($updateUserData->notify_new_orders !== null) {
            $this->accountUserRepository->updateWhere(
                attributes: ['notify_new_orders' => $updateUserData->notify_new_orders],
                where: [
                    'user_id' => $updateUserData->id,
                    'account_id' => $updateUserData->account_id,
                ],
            );
        }

        return $this->userRepository->findByIdAndAccountId($updateUserData->id, $updateUserData->account_id);
    }

    private function isChangingPassword(UpdateMeDTO $updateUserData): bool
    {
        return $updateUserData->password !== null && $updateUserData->current_password !== null;
    }

    /**
     * @throws PasswordInvalidException
     */
    private function validateCurrentPassword(UpdateMeDTO $updateUserData, UserDomainObject $existingUser): void
    {
        if ($existingUser->getPassword() === null
            || ! $this->hasher->check($updateUserData->current_password, $existingUser->getPassword())) {
            throw new PasswordInvalidException('Current password is invalid');
        }
    }

    /**
     * @throws IdentityConfirmationFailedException
     */
    private function confirmIdentity(UpdateMeDTO $updateUserData, UserDomainObject $existingUser): void
    {
        $this->identityConfirmationService->confirm(
            user: $existingUser,
            password: $updateUserData->confirmation_password,
            reauthProof: $updateUserData->reauth_proof,
            tokenId: $updateUserData->token_id,
        );
    }

    private function isChangingEmail(UpdateMeDTO $updateUserData, UserDomainObject $existingUser): bool
    {
        return $updateUserData->email !== null
            && strtolower(trim($updateUserData->email)) !== strtolower($existingUser->getEmail());
    }

    private function getExistingUser(UpdateMeDTO $updateUserData): UserDomainObject
    {
        return $this->userRepository->findByIdAndAccountId(
            $updateUserData->id,
            $updateUserData->account_id
        );
    }

    /**
     * @throws ResourceConflictException
     */
    private function assertEmailIsAvailable(string $email, int $userId): void
    {
        $userWithEmail = $this->userRepository->findFirstWhere([
            ['email', 'ilike', addcslashes($email, '\\%_')],
            ['id', '!=', $userId],
        ]);

        if ($userWithEmail !== null) {
            throw new ResourceConflictException(__('This email address is already in use.'));
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function assertEmailKeepsSsoDomain(int $userId, string $email): void
    {
        try {
            $this->ssoEnforcementService->assertEmailChangeAllowed($userId, $email);
        } catch (AccountSsoConfigurationException $exception) {
            throw new ResourceConflictException($exception->getMessage());
        }
    }

    private function notifyCurrentAddressOfEmailChange(UserDomainObject $existingUser): void
    {
        if ($existingUser->getEmailVerifiedAt() === null) {
            return;
        }

        $this->mailer
            ->to($existingUser->getEmail())
            ->locale($existingUser->getLocale())
            ->send(new EmailChangeRequestedMail);
    }

    private function sendEmailChangeConfirmation(UserDomainObject $existingUser, string $newEmail): void
    {
        $this->mailer
            ->to($newEmail)
            ->locale($existingUser->getLocale())
            ->send(new ConfirmEmailChangeMail($newEmail, $this->encryptedPayloadService->encryptPayload([
                'id' => $existingUser->getId(),
                'email' => $newEmail,
            ]))
            );
    }

    private function isUpdatingDetails(UpdateMeDTO $updateUserData): bool
    {
        return $updateUserData->first_name !== null || $updateUserData->last_name !== null || $updateUserData->timezone !== null || $updateUserData->email !== null;
    }
}
