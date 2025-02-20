<?php

namespace HiEvents\Services\Application\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Mail\User\ConfirmEmailChangeMail;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\DTO\UpdateMeDTO;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Mail\Mailer;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

readonly class UpdateMeHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private Hasher                  $hasher,
        private Mailer                  $mailer,
        private EncryptedPayloadService $encryptedPayloadService,
    )
    {
    }

    /**
     * @throws PasswordInvalidException
     */
    public function handle(UpdateMeDTO $updateUserData): UserDomainObject
    {
        $existingUser = $this->getExistingUser($updateUserData);
        $updateArray = [];

        if ($this->isChangingPassword($updateUserData)) {
            $this->validateCurrentPassword($updateUserData, $existingUser);
            $updateArray['password'] = $this->hasher->make($updateUserData->password);
        }

        if ($this->isUpdatingDetails($updateUserData)) {
            $updateArray = [
                'first_name' => $updateUserData->first_name,
                'last_name' => $updateUserData->last_name,
                'timezone' => $updateUserData->timezone,
                'locale' => $updateUserData->locale,
            ];

            if ($this->isChangingEmail($updateUserData, $existingUser)) {
                $updateArray['pending_email'] = $updateUserData->email;
                $this->sendEmailChangeConfirmation($existingUser);
            }
        }

        $this->userRepository->updateWhere(
            attributes: $updateArray,
            where: [
                'id' => $updateUserData->id,
            ]
        );

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
        if (!$this->hasher->check($updateUserData->current_password, $existingUser->getPassword())) {
            throw new PasswordInvalidException('Current password is invalid');
        }
    }

    private function isChangingEmail(UpdateMeDTO $updateUserData, UserDomainObject $existingUser): bool
    {
        return $updateUserData->email !== $existingUser->getEmail();
    }

    private function getExistingUser(UpdateMeDTO $updateUserData): UserDomainObject
    {
        $existingUser = $this->userRepository->findFirstWhere([
            'id' => $updateUserData->id,
        ]);

        if ($existingUser === null) {
            throw new ResourceNotFoundException();
        }

        return $existingUser;
    }

    private function sendEmailChangeConfirmation(UserDomainObject $existingUser): void
    {
        $this->mailer
            ->to($existingUser->getEmail())
            ->locale($existingUser->getLocale())
            ->send(new ConfirmEmailChangeMail($existingUser, $this->encryptedPayloadService->encryptPayload([
                    'id' => $existingUser->getId(),
                ]))
            );
    }

    /**
     * @param UpdateMeDTO $updateUserData
     * @return bool
     */
    private function isUpdatingDetails(UpdateMeDTO $updateUserData): bool
    {
        return $updateUserData->first_name !== null || $updateUserData->last_name !== null || $updateUserData->timezone !== null || $updateUserData->email !== null;
    }
}
