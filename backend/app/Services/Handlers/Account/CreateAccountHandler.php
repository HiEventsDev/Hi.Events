<?php

declare(strict_types=1);

namespace HiEvents\Services\Handlers\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\EmailAlreadyExists;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Account\AccountUserAssociationService;
use HiEvents\Services\Domain\User\EmailConfirmationService;
use HiEvents\Services\Handlers\Account\DTO\CreateAccountDTO;
use HiEvents\Services\Handlers\Account\Exceptions\AccountRegistrationDisabledException;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Hashing\HashManager;
use NumberFormatter;
use Throwable;

readonly class CreateAccountHandler
{
    public function __construct(
        private UserRepositoryInterface        $userRepository,
        private AccountRepositoryInterface     $accountRepository,
        private HashManager                    $hashManager,
        private DatabaseManager                $databaseManager,
        private Repository                     $config,
        private EmailConfirmationService       $emailConfirmationService,
        private AccountUserAssociationService  $accountUserAssociationService,
        private AccountUserRepositoryInterface $accountUserRepository,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CreateAccountDTO $accountData): AccountDomainObject
    {
        if ($this->config->get('app.disable_registration')) {
            throw new AccountRegistrationDisabledException();
        }

        $isSaasMode = $this->config->get('app.saas_mode_enabled');
        $passwordHash = $this->hashManager->make($accountData->password);

        return $this->databaseManager->transaction(function () use ($isSaasMode, $passwordHash, $accountData) {
            $account = $this->accountRepository->create([
                'timezone' => $this->getTimezone($accountData),
                'currency_code' => $this->getCurrencyCode($accountData),
                'name' => $accountData->first_name . ($accountData->last_name ? ' ' . $accountData->last_name : ''),
                'email' => strtolower($accountData->email),
                'short_id' => IdHelper::shortId(IdHelper::ACCOUNT_PREFIX),
                // If the app is not running in SaaS mode, we can immediately verify the account.
                // Same goes for the email verification below.
                'account_verified_at' => $isSaasMode ? null : now()->toDateTimeString(),
            ]);

            $user = $this->getExistingUser($accountData) ?? $this->userRepository->create([
                'password' => $passwordHash,
                'email' => strtolower($accountData->email),
                'first_name' => $accountData->first_name,
                'last_name' => $accountData->last_name,
                'timezone' => $this->getTimezone($accountData),
                'email_verified_at' => $isSaasMode ? null : now()->toDateTimeString(),
                'locale' => $accountData->locale,
            ]);

            $this->accountUserAssociationService->associate(
                user: $user,
                account: $account,
                role: Role::ADMIN,
                status: UserStatus::ACTIVE,
                isAccountOwner: true
            );

            $this->emailConfirmationService->sendConfirmation($user);

            return $account;
        });
    }

    private function getTimezone(CreateAccountDTO $accountData): ?string
    {
        return $accountData->timezone ?? $this->config->get('app.default_timezone');
    }

    private function getCurrencyCode(CreateAccountDTO $accountData): string
    {
        $defaultCurrency = $this->config->get('app.default_currency_code');

        if ($accountData->currency_code !== null) {
            return $accountData->currency_code;
        }

        if ($accountData->locale !== null) {
            $numberFormatter = new NumberFormatter($accountData->locale, NumberFormatter::CURRENCY);
            $guessedCode = $numberFormatter->getTextAttribute(NumberFormatter::CURRENCY_CODE);

            // 'XXX' denotes an unknown currency
            if ($guessedCode && $guessedCode !== 'XXX') {
                return $guessedCode;
            }
        }

        return $defaultCurrency;
    }

    /**
     * @throws EmailAlreadyExists
     */
    private function getExistingUser(CreateAccountDTO $accountData): ?UserDomainObject
    {
        $existingUser = $this->userRepository
            ->findFirstWhere([
                'email' => strtolower($accountData->email),
            ]);

        if ($existingUser === null) {
            return null;
        }

        $existingOwner = $this->accountUserRepository->findFirstWhere([
            'user_id' => $existingUser->getId(),
            'is_account_owner' => true,
        ]);

        if ($existingOwner !== null) {
            throw new EmailAlreadyExists(
                __('There is already an account associated with this email. Please log in instead.')
            );
        }

        return $existingUser;
    }
}
