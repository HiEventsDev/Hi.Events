<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Account;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\EmailAlreadyExists;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AccountAttributionRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\DTO\CreateAccountDTO;
use HiEvents\Services\Application\Handlers\Account\Exceptions\AccountConfigurationDoesNotExist;
use HiEvents\Services\Application\Handlers\Account\Exceptions\AccountRegistrationDisabledException;
use HiEvents\Services\Domain\Account\AccountUserAssociationService;
use HiEvents\Services\Domain\User\EmailConfirmationService;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Hashing\HashManager;
use NumberFormatter;
use Psr\Log\LoggerInterface;
use Throwable;

class CreateAccountHandler
{
    public function __construct(
        private readonly UserRepositoryInterface                 $userRepository,
        private readonly AccountRepositoryInterface              $accountRepository,
        private readonly HashManager                             $hashManager,
        private readonly DatabaseManager                         $databaseManager,
        private readonly Repository                              $config,
        private readonly EmailConfirmationService                $emailConfirmationService,
        private readonly AccountUserAssociationService           $accountUserAssociationService,
        private readonly AccountUserRepositoryInterface          $accountUserRepository,
        private readonly AccountConfigurationRepositoryInterface $accountConfigurationRepository,
        private readonly AccountAttributionRepositoryInterface   $accountAttributionRepository,
        private readonly LoggerInterface                         $logger,
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
        $passwordHash = $this->hashManager->make($accountData->password);;

        return $this->databaseManager->transaction(function () use ($isSaasMode, $passwordHash, $accountData) {
            $account = $this->accountRepository->create([
                'timezone' => $this->getTimezone($accountData),
                'currency_code' => $this->getCurrencyCode($accountData),
                'name' => $accountData->first_name . ($accountData->last_name ? ' ' . $accountData->last_name : ''),
                'email' => strtolower($accountData->email),
                'short_id' => IdHelper::shortId(IdHelper::ACCOUNT_PREFIX),
                'account_verified_at' => $isSaasMode ? null : now()->toDateTimeString(),
                'account_configuration_id' => $this->getAccountConfigurationId($accountData),
                'account_messaging_tier_id' => $this->getDefaultMessagingTierId(),
            ]);

            $user = $this->getExistingUser($accountData) ?? $this->userRepository->create([
                'password' => $passwordHash,
                'email' => strtolower($accountData->email),
                'first_name' => $accountData->first_name,
                'last_name' => $accountData->last_name,
                'timezone' => $this->getTimezone($accountData),
                'email_verified_at' => $isSaasMode ? null : now()->toDateTimeString(),
                'locale' => $accountData->locale,
                'marketing_opted_in_at' => $accountData->marketing_opt_in ? now()->toDateTimeString() : null,
            ]);

            $this->accountUserAssociationService->associate(
                user: $user,
                account: $account,
                role: Role::ADMIN,
                status: UserStatus::ACTIVE,
                isAccountOwner: true
            );

            if ($this->hasUtmData($accountData)) {
                $this->accountAttributionRepository->create([
                    'account_id' => $account->getId(),
                    'utm_source' => $this->normalizeUtmValue($accountData->utm_source),
                    'utm_medium' => $this->normalizeUtmValue($accountData->utm_medium),
                    'utm_campaign' => $this->normalizeUtmValue($accountData->utm_campaign),
                    'utm_term' => $this->normalizeUtmValue($accountData->utm_term),
                    'utm_content' => $this->normalizeUtmValue($accountData->utm_content),
                    'referrer_url' => $accountData->referrer_url,
                    'landing_page' => $accountData->landing_page,
                    'gclid' => $accountData->gclid,
                    'fbclid' => $accountData->fbclid,
                    'source_type' => $this->classifySourceType($accountData),
                    'utm_raw' => $accountData->utm_raw,
                ]);
            }

            $this->emailConfirmationService->sendConfirmation($user, $account->getId());

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

    /**
     * @throws AccountConfigurationDoesNotExist
     */
    private function getAccountConfigurationId(CreateAccountDTO $accountData): int
    {
        if ($accountData->invite_token !== null) {
            $decryptedInviteToken = decrypt($accountData->invite_token);
            $accountConfigurationId = $decryptedInviteToken['account_configuration_id'];

            $accountConfiguration = $this->accountConfigurationRepository->findFirstWhere([
                'id' => $accountConfigurationId,
            ]);

            if ($accountConfiguration !== null) {
                return $accountConfiguration->getId();
            }

            $this->logger->error('Invalid account configuration ID in invite token', [
                'account_configuration_id' => $accountConfigurationId,
            ]);
        }

        $defaultConfiguration = $this->accountConfigurationRepository->findFirstWhere([
            'is_system_default' => true,
        ]);

        if ($defaultConfiguration === null) {
            $this->logger->error('No default account configuration found');
            throw new AccountConfigurationDoesNotExist(
                __('There is no default account configuration available')
            );
        }

        return $defaultConfiguration->getId();
    }

    private function normalizeUtmValue(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return strtolower(trim($value));
    }

    private function hasUtmData(CreateAccountDTO $data): bool
    {
        return $data->utm_source !== null
            || $data->utm_medium !== null
            || $data->utm_campaign !== null
            || $data->gclid !== null
            || $data->fbclid !== null;
    }

    private function classifySourceType(CreateAccountDTO $data): string
    {
        if ($data->gclid !== null) {
            return 'paid';
        }

        if ($data->fbclid !== null) {
            return 'paid';
        }

        $paidMediums = ['cpc', 'ppc', 'paid', 'paidsocial', 'display', 'retargeting'];
        $normalizedMedium = $this->normalizeUtmValue($data->utm_medium);

        if ($normalizedMedium !== null && in_array($normalizedMedium, $paidMediums, true)) {
            return 'paid';
        }

        if ($data->referrer_url !== null && !$this->isInternalReferrer($data->referrer_url)) {
            return 'referral';
        }

        return 'organic';
    }

    private function isInternalReferrer(?string $referrer): bool
    {
        if ($referrer === null || trim($referrer) === '') {
            return false;
        }

        $appUrl = $this->config->get('app.url');

        if ($appUrl === null) {
            return false;
        }

        $appHost = parse_url($appUrl, PHP_URL_HOST);
        $referrerHost = parse_url($referrer, PHP_URL_HOST);

        return $appHost === $referrerHost;
    }

    private function getDefaultMessagingTierId(): int
    {
        // Self-hosted instances get Premium tier, SaaS gets Untrusted
        return $this->config->get('app.is_hi_events') ? 1 : 3;
    }
}
