<?php

declare(strict_types=1);

namespace HiEvents\Services\Handlers\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Exceptions\EmailAlreadyExists;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Common\User\EmailConfirmationService;
use HiEvents\Services\Handlers\Account\DTO\CreateAccountDTO;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Hashing\HashManager;
use NumberFormatter;
use Throwable;

readonly class CreateAccountHandler
{
    public function __construct(
        private UserRepositoryInterface    $userRepository,
        private AccountRepositoryInterface $accountRepository,
        private HashManager                $hashManager,
        private DatabaseManager            $databaseManager,
        private Repository                 $config,
        private EmailConfirmationService   $emailConfirmationService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CreateAccountDTO $accountData): AccountDomainObject
    {
        $passwordHash = $this->hashManager->make($accountData->password);

        return $this->databaseManager->transaction(function () use ($passwordHash, $accountData) {
            $account = $this->accountRepository->create([
                'timezone' => $this->getTimezone($accountData),
                'currency_code' => $this->getCurrencyCode($accountData),
                'name' => $accountData->first_name . ($accountData->last_name ? ' ' . $accountData->last_name : ''),
                'email' => strtolower($accountData->email),
                'short_id' => IdHelper::randomPrefixedId(IdHelper::ACCOUNT_PREFIX),
            ]);

            $existingUsers = $this->userRepository->findWhere([
                'email' => strtolower($accountData->email),
            ]);

            if ($existingUsers->isNotEmpty()) {
                throw new EmailAlreadyExists(__('This email address is already in use.'));
            }

            $user = $this->userRepository->create([
                'password' => $passwordHash,
                'account_id' => $account->getId(),
                'email' => strtolower($accountData->email),
                'first_name' => $accountData->first_name,
                'last_name' => $accountData->last_name,
                'timezone' => $this->getTimezone($accountData),
                'status' => UserStatus::ACTIVE->name,
                'role' => Role::ADMIN->name,
                'is_account_owner' => true,
            ]);

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
}
