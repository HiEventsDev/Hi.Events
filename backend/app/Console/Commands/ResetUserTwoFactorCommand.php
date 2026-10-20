<?php

namespace HiEvents\Console\Commands;

use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\ResetUserTwoFactorHandler;
use Illuminate\Console\Command;

class ResetUserTwoFactorCommand extends Command
{
    protected $signature = 'user:reset-two-factor {email : The email address of the user}';

    protected $description = 'Turn off two-factor authentication for a user who has lost access to their authenticator app and recovery codes.';

    public function handle(
        UserRepositoryInterface $userRepository,
        ResetUserTwoFactorHandler $resetUserTwoFactorHandler,
    ): int {
        $email = strtolower(trim($this->argument('email')));
        $user = $userRepository->findFirstWhere(['email' => $email]);

        if ($user === null) {
            $this->error("No user found with email: $email");

            return self::FAILURE;
        }

        if (! $this->confirm("Turn off two-factor authentication for {$user->getFullName()} ({$user->getEmail()})?", false)) {
            $this->info('Operation cancelled.');

            return self::FAILURE;
        }

        try {
            $resetUserTwoFactorHandler->handle($user->getId(), null);
        } catch (ResourceConflictException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Two-factor authentication has been turned off. The user has been notified by email.');

        return self::SUCCESS;
    }
}
