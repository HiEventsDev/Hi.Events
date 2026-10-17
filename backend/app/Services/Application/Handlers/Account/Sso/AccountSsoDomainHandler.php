<?php

namespace HiEvents\Services\Application\Handlers\Account\Sso;

use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountSsoDomainRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\Enterprise\AccountSsoDomainService;

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
