<?php

namespace HiEvents\Enterprise\Sso\Services\Domain;

use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoDomainRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\Network\HostResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class AccountSsoDomainService
{
    private const DOMAIN_PATTERN = '/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/';

    private const MAX_DOMAINS_PER_ACCOUNT = 20;

    public function __construct(
        private readonly AccountSsoDomainRepositoryInterface $domainRepository,
        private readonly HostResolver $hostResolver,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws AccountSsoConfigurationException
     */
    public function add(int $accountId, string $domain): AccountSsoDomainDomainObject
    {
        $domain = $this->normalise($domain);

        if (FreeMailDomains::contains($domain)) {
            throw new AccountSsoConfigurationException(__('Personal email domains like :domain can\'t be used for single sign-on.', ['domain' => $domain]), 'domain');
        }

        if ($this->domainRepository->findFirstWhere(['account_id' => $accountId, 'domain' => $domain]) !== null) {
            throw new AccountSsoConfigurationException(__('This domain has already been added.'), 'domain');
        }

        if ($this->domainRepository->countWhere(['account_id' => $accountId]) >= self::MAX_DOMAINS_PER_ACCOUNT) {
            throw new AccountSsoConfigurationException(__('You can add up to :count domains.', ['count' => self::MAX_DOMAINS_PER_ACCOUNT]), 'domain');
        }

        return $this->domainRepository->create([
            'account_id' => $accountId,
            'domain' => $domain,
            'verification_token' => Str::lower(Str::random(40)),
        ]);
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function verify(AccountSsoDomainDomainObject $domain): AccountSsoDomainDomainObject
    {
        if (! $this->txtRecordMatches($domain)) {
            throw new AccountSsoConfigurationException(
                __('We couldn\'t find the TXT record yet. DNS changes can take a while to appear, so try again in a few minutes.'),
                'domain',
            );
        }

        return $this->markVerified($domain);
    }

    public function findVerifiedElsewhere(AccountSsoDomainDomainObject $domain): ?AccountSsoDomainDomainObject
    {
        return $this->domainRepository->findFirstWhere([
            'domain' => $domain->getDomain(),
            ['verified_at', 'not null', null],
            ['account_id', '!=', $domain->getAccountId()],
        ]);
    }

    public function releaseVerificationElsewhere(AccountSsoDomainDomainObject $domain): void
    {
        $this->domainRepository->updateWhere(
            attributes: ['verified_at' => null, 'failing_since' => null],
            where: [
                'domain' => $domain->getDomain(),
                ['account_id', '!=', $domain->getAccountId()],
            ],
        );

        $this->logger->info('SSO domain verification released for another account', [
            'domain' => $domain->getDomain(),
            'account_id' => $domain->getAccountId(),
        ]);
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function markVerified(AccountSsoDomainDomainObject $domain): AccountSsoDomainDomainObject
    {
        $claimedElsewhere = $this->findVerifiedElsewhere($domain);

        if ($claimedElsewhere !== null) {
            $this->logger->warning('SSO domain verification conflict', [
                'domain' => $domain->getDomain(),
                'account_id' => $domain->getAccountId(),
                'verified_account_id' => $claimedElsewhere->getAccountId(),
            ]);

            throw new AccountSsoConfigurationException(
                __('This domain is already verified by another account. Contact support to review ownership.'),
                'domain',
            );
        }

        try {
            return $this->domainRepository->updateFromArray($domain->getId(), [
                'verified_at' => $domain->getVerifiedAt() ?? now()->toDateTimeString(),
                'last_checked_at' => now()->toDateTimeString(),
                'failing_since' => null,
            ]);
        } catch (QueryException) {
            throw new AccountSsoConfigurationException(
                __('This domain is already verified by another account. Contact support to review ownership.'),
                'domain',
            );
        }
    }

    public function recheck(AccountSsoDomainDomainObject $domain): AccountSsoDomainDomainObject
    {
        $matches = $this->txtRecordMatches($domain);

        if (! $matches) {
            $this->logger->info('SSO domain re-check failed', [
                'domain' => $domain->getDomain(),
                'account_id' => $domain->getAccountId(),
            ]);
        }

        return $this->domainRepository->updateFromArray($domain->getId(), [
            'last_checked_at' => now()->toDateTimeString(),
            'failing_since' => $matches ? null : ($domain->getFailingSince() ?? now()->toDateTimeString()),
        ]);
    }

    public function findUsableDomainForEmail(string $email): ?AccountSsoDomainDomainObject
    {
        $domain = $this->domainOf($email);

        if ($domain === null) {
            return null;
        }

        /** @var AccountSsoDomainDomainObject|null $record */
        $record = $this->domainRepository->findFirstWhere([
            'domain' => $domain,
            ['verified_at', 'not null', null],
        ]);

        return $record !== null && $record->isUsable() ? $record : null;
    }

    public function isEmailOnUsableDomain(int $accountId, ?string $email): bool
    {
        $record = $email === null ? null : $this->findUsableDomainForEmail($email);

        return $record !== null && $record->getAccountId() === $accountId;
    }

    /**
     * @return Collection<int, AccountSsoDomainDomainObject>
     */
    public function usableDomains(int $accountId): Collection
    {
        return $this->domainRepository
            ->findWhere(['account_id' => $accountId, ['verified_at', 'not null', null]])
            ->filter(fn (AccountSsoDomainDomainObject $domain) => $domain->isUsable())
            ->values();
    }

    public function domainOf(string $email): ?string
    {
        $position = strrpos($email, '@');

        return $position === false ? null : strtolower(substr($email, $position + 1));
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    private function normalise(string $domain): string
    {
        $domain = rtrim(strtolower(trim($domain)), '.');

        if (str_contains($domain, '@')) {
            $domain = (string) $this->domainOf($domain);
        }

        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $domain) === 1) {
            $domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
        }

        if (preg_match(self::DOMAIN_PATTERN, $domain) !== 1) {
            throw new AccountSsoConfigurationException(__('Enter a domain like example.com.'), 'domain');
        }

        return $domain;
    }

    private function txtRecordMatches(AccountSsoDomainDomainObject $domain): bool
    {
        $expected = $domain->getTxtRecordValue();

        foreach ($this->hostResolver->txtRecords($domain->getTxtRecordName()) as $record) {
            if (hash_equals($expected, trim($record))) {
                return true;
            }
        }

        return false;
    }
}
