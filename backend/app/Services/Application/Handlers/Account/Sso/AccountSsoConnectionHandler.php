<?php

namespace HiEvents\Services\Application\Handlers\Account\Sso;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\Enums\SsoIntent;
use HiEvents\DomainObjects\Status\AccountSsoConnectionStatus;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Exceptions\SsoException;
use HiEvents\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountSsoDomainRepositoryInterface;
use HiEvents\Repository\Interfaces\UserIdentityRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Sso\DTO\AccountSsoOverview;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\StartSsoDTO;
use HiEvents\Services\Application\Handlers\Auth\Sso\StartSsoHandler;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoStartResult;
use HiEvents\Services\Domain\Auth\Sso\Enterprise\AccountSsoConnectionService;
use HiEvents\Services\Domain\Auth\Sso\Enterprise\DTO\AccountSsoConnectionData;
use HiEvents\Services\Domain\Auth\Sso\Enterprise\SsoSettingsChangeNotifier;
use HiEvents\Services\Domain\Auth\Sso\OidcClient;
use Illuminate\Database\DatabaseManager;
use Throwable;

class AccountSsoConnectionHandler
{
    public function __construct(
        private readonly AccountSsoConnectionRepositoryInterface $connectionRepository,
        private readonly AccountSsoDomainRepositoryInterface $domainRepository,
        private readonly UserIdentityRepositoryInterface $userIdentityRepository,
        private readonly AccountSsoConnectionService $connectionService,
        private readonly StartSsoHandler $startSsoHandler,
        private readonly OidcClient $oidcClient,
        private readonly DatabaseManager $databaseManager,
        private readonly SsoSettingsChangeNotifier $changeNotifier,
    ) {}

    public function overview(int $accountId): AccountSsoOverview
    {
        $connection = $this->find($accountId);

        return new AccountSsoOverview(
            connection: $connection,
            domains: $this->domainRepository->findWhere(['account_id' => $accountId])->sortBy(fn ($domain) => $domain->getDomain())->values(),
            redirectUri: $this->oidcClient->redirectUri(),
            ssoSignInsLast30Days: $connection === null ? 0 : $this->userIdentityRepository->countWhere([
                'account_sso_connection_id' => $connection->getId(),
                ['last_used_at', '>=', now()->subDays(30)->toDateTimeString()],
            ]),
        );
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function create(int $accountId, int $actorUserId, AccountSsoConnectionData $data): AccountSsoConnectionDomainObject
    {
        $connection = $this->connectionService->create($accountId, $data);

        $this->changeNotifier->notifyOwner($accountId, $actorUserId, __('Connected the identity provider :issuer', ['issuer' => $connection->getIssuer()]));

        return $connection;
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function update(int $accountId, int $actorUserId, AccountSsoConnectionData $data): AccountSsoConnectionDomainObject
    {
        $before = $this->findOrFail($accountId);
        $after = $this->connectionService->update($before, $data);

        if ($before->getIssuer() !== $after->getIssuer() || $before->getClientId() !== $after->getClientId()) {
            $this->changeNotifier->notifyOwner($accountId, $actorUserId, __('Changed the identity provider to :issuer', ['issuer' => $after->getIssuer()]));
        }

        return $after;
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function check(AccountSsoConnectionData $data): void
    {
        $this->connectionService->checkIdentityProvider($data);
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function setStatus(int $accountId, int $actorUserId, AccountSsoConnectionStatus $status): AccountSsoConnectionDomainObject
    {
        $connection = $this->findOrFail($accountId);

        if ($status === AccountSsoConnectionStatus::ACTIVE) {
            $activated = $this->connectionService->activate($connection);
            $this->changeNotifier->notifyOwner($accountId, $actorUserId, __('Turned on sign-in with :issuer', ['issuer' => $connection->getIssuer()]));

            return $activated;
        }

        if ($connection->getEnforceSso()) {
            throw new AccountSsoConfigurationException(__('Stop requiring single sign-on before turning off the connection.'), 'status');
        }

        return $this->connectionService->deactivate($connection);
    }

    /**
     * @throws AccountSsoConfigurationException
     * @throws Throwable
     */
    public function delete(int $accountId, int $actorUserId): void
    {
        $connection = $this->findOrFail($accountId);

        $this->databaseManager->transaction(fn () => $this->connectionService->delete($connection));
        $this->changeNotifier->notifyOwner($accountId, $actorUserId, __('Deleted the single sign-on connection'));
    }

    /**
     * @throws AccountSsoConfigurationException
     * @throws SsoException
     */
    public function startTest(int $accountId, int $userId, ?string $tokenId, ?string $returnPath): SsoStartResult
    {
        return $this->startSsoHandler->handle(new StartSsoDTO(
            providerKey: $this->findOrFail($accountId)->getProviderKey(),
            intent: SsoIntent::TEST,
            returnPath: $returnPath,
            userId: $userId,
            tokenId: $tokenId,
        ));
    }

    private function find(int $accountId): ?AccountSsoConnectionDomainObject
    {
        return $this->connectionRepository->findFirstWhere(['account_id' => $accountId]);
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    private function findOrFail(int $accountId): AccountSsoConnectionDomainObject
    {
        return $this->find($accountId)
            ?? throw new AccountSsoConfigurationException(__('Set up a single sign-on connection first.'));
    }
}
