<?php

namespace TicketKitten\Service\Handler\Auth;

use Illuminate\Database\DatabaseManager;
use Illuminate\Mail\Mailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;
use TicketKitten\Mail\ForgotPassword;
use TicketKitten\Repository\Interfaces\PasswordResetTokenRepositoryInterface;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;
use TicketKitten\Service\Common\TokenGeneratorService;

class ForgotPasswordHandler
{
    private UserRepositoryInterface $userRepository;

    private Mailer $mailer;

    private LoggerInterface $logger;

    private PasswordResetTokenRepositoryInterface $passwordResetTokenRepository;

    private TokenGeneratorService $tokenGeneratorService;

    private DatabaseManager $databaseManager;

    public function __construct(
        UserRepositoryInterface               $userRepository,
        Mailer                                $mailer,
        LoggerInterface                       $logger,
        PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        TokenGeneratorService                 $tokenGeneratorService,
        DatabaseManager                       $databaseManager,
    )
    {
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->passwordResetTokenRepository = $passwordResetTokenRepository;
        $this->tokenGeneratorService = $tokenGeneratorService;
        $this->databaseManager = $databaseManager;
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

    private function sendResetPasswordEmail($user, string $token): void
    {
        $this->logger->info('resetting password for user', [
            'user' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        $this->mailer->to($user->getEmail())->send(new ForgotPassword(
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
