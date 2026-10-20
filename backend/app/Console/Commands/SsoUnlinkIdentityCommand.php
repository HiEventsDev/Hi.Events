<?php

namespace HiEvents\Console\Commands;

use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\UserIdentityService;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use Illuminate\Console\Command;

class SsoUnlinkIdentityCommand extends Command
{
    protected $signature = 'sso:unlink-identity {email : The email address of the user}';

    protected $description = 'Remove every single sign-on login from a user, for example when their identity provider account was compromised or replaced.';

    public function handle(
        UserRepositoryInterface $userRepository,
        UserIdentityService $userIdentityService,
        UserSessionRevocationService $sessionRevocationService,
    ): int {
        $email = (string) $this->argument('email');
        $users = $userRepository->findByEmailIgnoringCase($email);

        if ($users->count() !== 1) {
            $this->error($users->isEmpty() ? "No user found with email: $email" : "More than one user has the email: $email");

            return self::FAILURE;
        }

        $user = $users->first();

        if (! $this->confirm("Remove all single sign-on logins from {$user->getFullName()} ({$user->getEmail()}) and sign them out everywhere?", false)) {
            $this->info('Operation cancelled.');

            return self::FAILURE;
        }

        $userIdentityService->deleteForUser($user->getId());
        $sessionRevocationService->revokeAll($user->getId());

        $this->info('Single sign-on logins removed and the user is signed out everywhere. They can set a password with "Forgot password" unless password sign-in is turned off or every account they belong to requires single sign-on.');

        return self::SUCCESS;
    }
}
