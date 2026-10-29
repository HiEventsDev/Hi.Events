<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Sso\Jobs;

use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoDomainRepositoryInterface;
use HiEvents\Enterprise\Sso\Services\Domain\AccountSsoDomainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecheckSsoDomainsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AccountSsoDomainRepositoryInterface $domainRepository, AccountSsoDomainService $domainService): void
    {
        $domainRepository
            ->findWhere([['verified_at', 'not null', null]])
            ->each(fn (AccountSsoDomainDomainObject $domain) => $domainService->recheck($domain));
    }
}
