<?php

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Repository\Interfaces\AccountSsoDomainRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\Enterprise\AccountSsoDomainService;
use Illuminate\Console\Command;

class SsoVerifyDomainCommand extends Command
{
    protected $signature = 'sso:verify-domain {domain : The domain to mark as verified} {--account= : The account that owns the domain}';

    protected $description = 'Mark a single sign-on domain as verified without a DNS check, after reviewing ownership manually.';

    public function handle(AccountSsoDomainRepositoryInterface $domainRepository, AccountSsoDomainService $domainService): int
    {
        $where = ['domain' => strtolower((string) $this->argument('domain'))];

        if ($this->option('account') !== null) {
            $where['account_id'] = (int) $this->option('account');
        }

        $domains = $domainRepository->findWhere($where);

        if ($domains->count() !== 1) {
            $this->error($domains->isEmpty()
                ? 'No account has added this domain.'
                : 'More than one account has added this domain. Pass --account to choose one.');

            return self::FAILURE;
        }

        /** @var AccountSsoDomainDomainObject $domain */
        $domain = $domains->first();

        if (! $this->confirm("Mark {$domain->getDomain()} as verified for account {$domain->getAccountId()}?", false)) {
            $this->info('Operation cancelled.');

            return self::FAILURE;
        }

        try {
            $domainService->markVerified($domain);
        } catch (AccountSsoConfigurationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$domain->getDomain()} is now verified for account {$domain->getAccountId()}.");

        return self::SUCCESS;
    }
}
