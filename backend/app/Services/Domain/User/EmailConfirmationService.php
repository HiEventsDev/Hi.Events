<?php

namespace HiEvents\Services\Domain\User;

use Carbon\Carbon;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Mail\Account\ConfirmEmailAddressEmail;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

readonly class EmailConfirmationService
{
    public function __construct(
        private Mailer                         $mailer,
        private EncryptedPayloadService        $encryptedPayloadService,
        private UserRepositoryInterface        $userRepository,
        private AccountRepositoryInterface     $accountRepository,
        private DatabaseManager                $databaseManager,
        private AccountUserRepositoryInterface $accountUserRepository,
    )
    {
    }

    /**
     * @throws DecryptionFailedException
     * @throws EncryptedPayloadExpiredException|Throwable
     */
    public function confirmEmailAddress(string $token, int $accountId): void
    {
        $this->databaseManager->transaction(function () use ($accountId, $token) {
            ['id' => $userId] = $this->encryptedPayloadService->decryptPayload($token);

            $user = $this->userRepository->findByIdAndAccountId($userId, $accountId);

            $this->verifyAccountEmail($user, $accountId);
        });
    }

    public function sendConfirmation(UserDomainObject $user): void
    {
        $token = $this->encryptedPayloadService->encryptPayload([
            'id' => $user->getId(),
        ], Carbon::now()->addMonths(6));

        $this->mailer
            ->to($user->getEmail())
            ->send(new ConfirmEmailAddressEmail($user, $token));
    }

    private function verifyAccountEmail(UserDomainObject $user, int $accountId): void
    {
        $this->userRepository->updateWhere(
            attributes: [
                'email_verified_at' => now(),
            ],
            where: [
                'id' => $user->getId(),
            ],
        );

        $accountUser = $this->accountUserRepository->findFirstWhere(
            where: [
                'user_id' => $user->getId(),
                'account_id' => $accountId,
            ]
        );

        if ($accountUser === null) {
            throw new ResourceNotFoundException();
        }

        if ($accountUser->getIsAccountOwner()) {
            $this->accountRepository->updateWhere(
                attributes: [
                    'account_verified_at' => now(),
                ],
                where: [
                    'id' => $accountId,
                ]
            );
        }
    }
}
