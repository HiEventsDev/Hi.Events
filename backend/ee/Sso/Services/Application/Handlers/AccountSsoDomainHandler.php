<?php

namespace HiEvents\Enterprise\Sso\Services\Application\Handlers;

use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoDomainRepositoryInterface;
use HiEvents\Enterprise\Sso\Services\Domain\AccountSsoDomainService;

class AccountSsoDomainHandler
{
    public function __construct(
        private readonly AccountSsoDomainRepositoryInterface $domainRepository,
        private readonly AccountSsoConnectionRepositoryInterface $connectionRepository,
        private readonly AccountSsoDomainService $domainService,
    ) {}

    /**
     * @throws AccountSsoConfigurationException
     */
    public function add(int $accountId, string $domain): AccountSsoDomainDomainObject
    {
        return $this->domainService->add($accountId, $domain);
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function verify(int $accountId, int $domainId): AccountSsoDomainDomainObject
    {
        return $this->domainService->verify($this->findOrFail($accountId, $domainId));
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function delete(int $accountId, int $domainId): void
    {
        $domain = $this->findOrFail($accountId, $domainId);

        if ($domain->getVerifiedAt() !== null && $this->connectionRepository->findFirstWhere(['account_id' => $accountId])?->getEnforceSso() === true) {
            throw new AccountSsoConfigurationException(__('Stop requiring single sign-on before removing a verified domain.'), 'domain');
        }

        $this->domainRepository->deleteById($domain->getId());
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    private function findOrFail(int $accountId, int $domainId): AccountSsoDomainDomainObject
    {
        return $this->domainRepository->findFirstWhere(['id' => $domainId, 'account_id' => $accountId])
            ?? throw new AccountSsoConfigurationException(__('Domain not found.'), 'domain');
    }
}
