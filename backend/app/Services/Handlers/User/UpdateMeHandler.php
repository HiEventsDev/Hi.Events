<?php

namespace HiEvents\Services\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\PasswordInvalidException;
use HiEvents\Mail\ConfirmEmailChangeMail;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Common\EncryptedPayloadService;
use HiEvents\Services\Handlers\User\DTO\UpdateMeDTO;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Mail\Mailer;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class UpdateMeHandler
{
    private UserRepositoryInterface $userRepository;

    private Hasher $hasher;

    private Mailer $mailer;

    private EncryptedPayloadService $encryptedPayloadService;

    public function __construct(
        UserRepositoryInterface $userRepository,
        Hasher                  $hasher,
        Mailer                  $mailer,
        EncryptedPayloadService $encryptedPayloadService,
    )
    {
        $this->userRepository = $userRepository;
        $this->hasher = $hasher;
        $this->mailer = $mailer;
        $this->encryptedPayloadService = $encryptedPayloadService;
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
            ];

            if ($this->isChangingEmail($updateUserData, $existingUser)) {
                $updateArray['pending_email'] = $updateUserData->email;
                $this->sendEmailChangeConfirmation($updateUserData, $existingUser);
            }
        }

        $this->userRepository->updateWhere(
            attributes: $updateArray,
            where: [
                'id' => $updateUserData->id,
                'account_id' => $updateUserData->account_id,
            ]
        );

        return $this->userRepository->findById($updateUserData->id);
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
            'account_id' => $updateUserData->account_id,
        ]);

        if ($existingUser === null) {
            throw new ResourceNotFoundException();
        }

        return $existingUser;
    }

    private function sendEmailChangeConfirmation(UpdateMeDTO $updateUserData, UserDomainObject $existingUser): void
    {
        $this->mailer
            ->to($updateUserData->email)
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
