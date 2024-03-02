<?php

namespace HiEvents\Services\Common\User;

use Carbon\Carbon;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\DecryptionFailedException;
use HiEvents\Exceptions\EncryptedPayloadExpiredException;
use HiEvents\Mail\ConfirmEmailAddressEmail;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Common\EncryptedPayloadService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Throwable;

readonly class EmailConfirmationService
{
    public function __construct(
        private Mailer                     $mailer,
        private EncryptedPayloadService    $encryptedPayloadService,
        private UserRepositoryInterface    $userRepository,
        private AccountRepositoryInterface $accountRepository,
        private DatabaseManager            $databaseManager,
    )
    {
    }

    /**
     * @throws DecryptionFailedException
     * @throws EncryptedPayloadExpiredException|Throwable
     */
    public function confirmEmailAddress(string $token): void
    {
        $this->databaseManager->transaction(function () use ($token) {
            ['email' => $email] = $this->encryptedPayloadService->decryptPayload($token);

            $user = $this->userRepository->findFirstWhere(
                where: [
                    'email' => $email,
                ]
            );

            $this->userRepository->updateWhere(
                attributes: [
                    'email_verified_at' => now(),
                ],
                where: [
                    'email' => $email,
                ]
            );

            if ($user->getIsAccountOwner()) {
                $this->accountRepository->updateWhere(
                    attributes: [
                        'account_verified_at' => now(),
                    ],
                    where: [
                        'id' => $user->getAccountId(),
                    ]
                );
            }
        });
    }

    public function sendConfirmation(UserDomainObject $user): void
    {
        $token = $this->encryptedPayloadService->encryptPayload([
            'email' => $user->getEmail(),
        ], Carbon::now()->addMonths(6));

        $this->mailer
            ->to($user->getEmail())
            ->send(new ConfirmEmailAddressEmail($user, $token));
    }
}
