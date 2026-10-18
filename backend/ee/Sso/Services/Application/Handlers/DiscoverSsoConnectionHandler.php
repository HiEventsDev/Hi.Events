<?php

namespace HiEvents\Enterprise\Sso\Services\Application\Handlers;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Enterprise\Sso\Services\Domain\AccountSsoDomainService;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\SsoProviderRegistry;

class DiscoverSsoConnectionHandler
{
    public function __construct(
        private readonly AccountSsoDomainService $domainService,
        private readonly AccountSsoConnectionRepositoryInterface $connectionRepository,
        private readonly SsoProviderRegistry $providerRegistry,
    ) {}

    /**
     * @throws SsoException
     */
    public function handle(string $email): SsoProviderConfig
    {
        $domain = $this->domainService->findUsableDomainForEmail(strtolower(trim($email)));

        /** @var AccountSsoConnectionDomainObject|null $connection */
        $connection = $domain === null ? null : $this->connectionRepository->findFirstWhere(['account_id' => $domain->getAccountId()]);

        if ($connection === null || ! $connection->isActive()) {
            throw new SsoException(SsoErrorCode::NOT_SET_UP, __('Single sign-on isn\'t set up for this email address.'));
        }

        return $this->providerRegistry->forConnection($connection);
    }
}
