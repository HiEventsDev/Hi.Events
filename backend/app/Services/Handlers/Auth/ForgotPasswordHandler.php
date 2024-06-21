<?php

namespace HiEvents\Services\Handlers\Auth;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Mail\User\ForgotPassword;
use HiEvents\Repository\Interfaces\PasswordResetTokenRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Infrastructure\TokenGenerator\TokenGeneratorService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Mail\Mailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class ForgotPasswordHandler
{
    public function __construct(
        private readonly UserRepositoryInterface               $userRepository,
        private readonly Mailer                                $mailer,
        private readonly LoggerInterface                       $logger,
        private readonly PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        private readonly TokenGeneratorService                 $tokenGeneratorService,
        private readonly DatabaseManager                       $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(string $email): void
    {
        $email = strtolower($email);
        $this->databaseManager->transaction(function () use ($email) {
            $user = $this->findUserByEmail($email);
            $token = $this->generateAndSaveResetToken($email);
            $this->sendResetPasswordEmail($user, $token);
        });
    }

    private function findUserByEmail(string $email)
    {
        $user = $this->userRepository->findFirstWhere([
                'email' => strtolower($email)
            ]
        );

        if ($user === null) {
            $this->logUnrecognisedEmail($email);
            throw new ResourceNotFoundException();
        }

        return $user;
    }

    private function generateAndSaveResetToken(string $email): string
    {
        $token = $this->tokenGeneratorService->generateToken(prefix: 'rp');

        $this->passwordResetTokenRepository->deleteWhere(['email' => $email]);
        $this->passwordResetTokenRepository->create([
            'email' => $email,
            'token' => $token,
        ]);

        return $token;
    }

    private function sendResetPasswordEmail(UserDomainObject $user, string $token): void
    {
        $this->logger->info('resetting password for user', [
            'user' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        $this->mailer
            ->to($user->getEmail())
            ->locale($user->getLocale())
            ->send(new ForgotPassword(
                user: $user,
                token: $token,
            ));
    }

    private function logUnrecognisedEmail(string $email): void
    {
        $this->logger->info('unrecognised email for password reset', [
            'email' => $email,
        ]);
    }
}
