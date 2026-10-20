<?php

namespace HiEvents\Enterprise\Sso\Console;

use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoDomainRepositoryInterface;
use HiEvents\Enterprise\Sso\Services\Domain\AccountSsoDomainService;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;

class SsoVerifyDomainCommand extends Command
{
    protected $signature = 'sso:verify-domain {domain : The domain to mark as verified} {--account= : The account that owns the domain}';

    protected $description = 'Mark a single sign-on domain as verified without a DNS check, after reviewing ownership manually.';

    public function handle(
        AccountSsoDomainRepositoryInterface $domainRepository,
        AccountSsoDomainService $domainService,
        AccountSsoConnectionRepositoryInterface $connectionRepository,
        DatabaseManager $databaseManager,
    ): int {
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

        $holder = $domainService->findVerifiedElsewhere($domain);
        $question = $holder === null
            ? "Mark {$domain->getDomain()} as verified for account {$domain->getAccountId()}?"
            : "{$domain->getDomain()} is verified by account {$holder->getAccountId()}. Remove it from that account and verify it for account {$domain->getAccountId()}?";

        if ($holder !== null && $connectionRepository->findFirstWhere(['account_id' => $holder->getAccountId()])?->getEnforceSso() === true) {
            $this->warn("Account {$holder->getAccountId()} requires single sign-on. Its members on {$domain->getDomain()} will be able to sign in with a password again.");
        }

        if (! $this->confirm($question, false)) {
            $this->info('Operation cancelled.');

            return self::FAILURE;
        }

        try {
            $databaseManager->transaction(function () use ($domainService, $domain, $holder) {
                if ($holder !== null) {
                    $domainService->releaseVerificationElsewhere($domain);
                }

                $domainService->markVerified($domain);
            });
        } catch (AccountSsoConfigurationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$domain->getDomain()} is now verified for account {$domain->getAccountId()}.");
        $record = AccountSsoDomainDomainObject::TXT_RECORD_PREFIX.$domain->getDomain();
        $this->warn("The daily check suspends the domain after 7 days without the {$record} TXT record, so ask the account to keep it in place.");

        return self::SUCCESS;
    }
}
