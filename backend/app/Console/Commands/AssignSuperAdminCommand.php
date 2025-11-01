<?php

namespace HiEvents\Console\Commands;

use Exception;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Console\Command;
use Psr\Log\LoggerInterface;

class AssignSuperAdminCommand extends Command
{
    protected $signature = 'user:make-superadmin {userId : The ID of the user to make a superadmin}';

    protected $description = 'Assign SUPERADMIN role to a user. WARNING: This grants complete system access.';

    public function __construct(
        private readonly UserRepositoryInterface        $userRepository,
        private readonly AccountUserRepositoryInterface $accountUserRepository,
        private readonly LoggerInterface                $logger,
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->argument('userId');

        $this->warn('⚠️  WARNING: This command will grant COMPLETE SYSTEM ACCESS to the user.');
        $this->warn('⚠️  SUPERADMIN users have unrestricted access to all accounts and data.');
        $this->newLine();

        if (!$this->confirm('Are you sure you want to proceed?', false)) {
            $this->info('Operation cancelled.');
            return self::FAILURE;
        }

        try {
            $user = $this->userRepository->findById((int)$userId);
        } catch (Exception $exception) {
            $this->error("Error finding user with ID: $userId" . " Message: " . $exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Found user: {$user->getFullName()} ({$user->getEmail()})");
        $this->newLine();

        if (!$this->confirm('Confirm assigning SUPERADMIN role to this user?', false)) {
            $this->info('Operation cancelled.');
            return self::FAILURE;
        }

        $accountUsers = $this->accountUserRepository->findWhere([
            'user_id' => $userId,
        ]);

        if ($accountUsers->isEmpty()) {
            $this->error('User is not associated with any accounts.');
            return self::FAILURE;
        }

        $updatedCount = 0;
        foreach ($accountUsers as $accountUser) {
            if ($accountUser->getRole() === Role::SUPERADMIN->name) {
                $this->comment("User already has SUPERADMIN role for account ID: {$accountUser->getAccountId()}");
                continue;
            }

            $this->accountUserRepository->updateWhere(
                attributes: [
                    'role' => Role::SUPERADMIN->name,
                ],
                where: [
                    'id' => $accountUser->getId(),
                ]
            );

            $updatedCount++;

            $this->logger->critical('SUPERADMIN role assigned via console command', [
                'user_id' => $userId,
                'user_email' => $user->getEmail(),
                'account_id' => $accountUser->getAccountId(),
                'previous_role' => $accountUser->getRole(),
                'command' => $this->signature,
            ]);
        }

        $this->newLine();
        $this->info("✓ Successfully assigned SUPERADMIN role to user across $updatedCount account(s).");
        $this->warn("⚠️  User {$user->getFullName()} now has COMPLETE SYSTEM ACCESS.");

        $this->logger->critical('SUPERADMIN role assignment completed', [
            'user_id' => $userId,
            'accounts_updated' => $updatedCount,
        ]);

        return self::SUCCESS;
    }
}
