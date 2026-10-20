<?php

namespace HiEvents\Enterprise\Sso\Services\Domain;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\Enums\AccountSsoConnectionType;
use HiEvents\DomainObjects\Status\AccountSsoConnectionStatus;
use HiEvents\DomainObjects\UserIdentityDomainObject;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Enterprise\Sso\Services\Domain\DTO\AccountSsoConnectionData;
use HiEvents\Exceptions\SsoException;
use HiEvents\Repository\Interfaces\UserIdentityRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\OidcDiscoveryClient;
use HiEvents\Services\Domain\Auth\Sso\SsoProviderRegistry;
use HiEvents\Services\Domain\Auth\UserSessionRevocationService;
use Psr\Log\LoggerInterface;

class AccountSsoConnectionService
{
    private const TENANT_ID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private const RESERVED_ISSUER_HOSTS = ['accounts.google.com', 'login.microsoftonline.com', 'login.live.com', 'sts.windows.net'];

    public function __construct(
        private readonly AccountSsoConnectionRepositoryInterface $connectionRepository,
        private readonly UserIdentityRepositoryInterface $userIdentityRepository,
        private readonly OidcDiscoveryClient $discoveryClient,
        private readonly SsoProviderRegistry $providerRegistry,
        private readonly UserSessionRevocationService $sessionRevocationService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws AccountSsoConfigurationException
     */
    public function create(int $accountId, AccountSsoConnectionData $data): AccountSsoConnectionDomainObject
    {
        if ($this->connectionRepository->findFirstWhere(['account_id' => $accountId]) !== null) {
            throw new AccountSsoConfigurationException(__('This account already has a single sign-on connection.'));
        }

        if ($data->clientSecret === null || trim($data->clientSecret) === '') {
            throw new AccountSsoConfigurationException(__('Enter the client secret.'), 'client_secret');
        }

        $issuer = $this->validateIdentityProvider($data, $data->clientSecret);

        return $this->connectionRepository->create([
            'account_id' => $accountId,
            ...$this->identityAttributes($data, $issuer),
            'client_secret' => trim($data->clientSecret),
            ...$this->policyAttributes($data),
            'status' => AccountSsoConnectionStatus::INACTIVE->value,
        ]);
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function update(AccountSsoConnectionDomainObject $connection, AccountSsoConnectionData $data): AccountSsoConnectionDomainObject
    {
        $secret = $data->clientSecret !== null && trim($data->clientSecret) !== '' ? trim($data->clientSecret) : null;
        $issuer = AccountSsoProviderFactory::issuerFor($data->type, $data->issuer, $data->tenantId);
        $identityChanged = $data->type->value !== $connection->getType()
            || $issuer !== $connection->getIssuer()
            || trim($data->clientId) !== $connection->getClientId()
            || $this->nullableLower($data->tenantId) !== $connection->getTenantId()
            || $this->nullableLower($data->hostedDomain) !== $connection->getHostedDomain();

        $attributes = $this->policyAttributes($data);

        if ($identityChanged) {
            if ($connection->getEnforceSso()) {
                throw new AccountSsoConfigurationException(__('Stop requiring single sign-on before changing the identity provider details.'));
            }

            $attributes = [
                ...$attributes,
                ...$this->identityAttributes($data, $this->validateIdentityProvider($data, $secret ?? $connection->getClientSecret())),
                'status' => AccountSsoConnectionStatus::INACTIVE->value,
                'last_tested_at' => null,
            ];

            $this->sessionRevocationService->revokeAllForUsers($this->identityUserIds($connection), $connection->getAccountId());
        }

        if ($secret !== null) {
            $attributes['client_secret'] = $secret;
        }

        return $this->connectionRepository->updateFromArray($connection->getId(), $attributes);
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function checkIdentityProvider(AccountSsoConnectionData $data): void
    {
        $this->validateIdentityProvider($data, 'unused');
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function activate(AccountSsoConnectionDomainObject $connection): AccountSsoConnectionDomainObject
    {
        if ($connection->getLastTestedAt() === null) {
            throw new AccountSsoConfigurationException(__('Run a successful test sign-in before turning on the connection.'), 'status');
        }

        return $this->connectionRepository->updateFromArray($connection->getId(), [
            'status' => AccountSsoConnectionStatus::ACTIVE->value,
        ]);
    }

    public function deactivate(AccountSsoConnectionDomainObject $connection): AccountSsoConnectionDomainObject
    {
        $updated = $this->connectionRepository->updateFromArray($connection->getId(), [
            'status' => AccountSsoConnectionStatus::INACTIVE->value,
        ]);

        $this->sessionRevocationService->revokeAllForUsers($this->identityUserIds($connection), $connection->getAccountId());

        $this->logger->info('SSO connection deactivated', [
            'account_id' => $connection->getAccountId(),
            'connection_id' => $connection->getId(),
        ]);

        return $updated;
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    public function delete(AccountSsoConnectionDomainObject $connection): void
    {
        if ($connection->getEnforceSso()) {
            throw new AccountSsoConfigurationException(__('Stop requiring single sign-on before deleting the connection.'));
        }

        $this->sessionRevocationService->revokeAllForUsers($this->identityUserIds($connection), $connection->getAccountId());
        $this->userIdentityRepository->deleteWhere(['account_sso_connection_id' => $connection->getId()]);
        $this->connectionRepository->deleteById($connection->getId());

        $this->logger->info('SSO connection deleted', [
            'account_id' => $connection->getAccountId(),
            'connection_id' => $connection->getId(),
        ]);
    }

    public function accountIdFor(int $connectionId): int
    {
        return $this->connectionRepository->findById($connectionId)->getAccountId();
    }

    public function markTested(int $connectionId): void
    {
        $this->connectionRepository->updateWhere(
            attributes: ['last_tested_at' => now()->toDateTimeString()],
            where: ['id' => $connectionId],
        );
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    private function validateIdentityProvider(AccountSsoConnectionData $data, string $clientSecret): string
    {
        $issuer = AccountSsoProviderFactory::issuerFor($data->type, $data->issuer, $data->tenantId);

        $this->assertTypeSpecificFields($data, $issuer);

        try {
            $this->discoveryClient->discover(new SsoProviderConfig(
                key: 'validation',
                type: $data->type->providerType(),
                name: $data->type->label(),
                issuer: (string) $issuer,
                clientId: trim($data->clientId),
                clientSecret: $clientSecret,
                logoUrl: null,
                trustEmail: false,
                restrictNetwork: ! $this->providerRegistry->allowsPrivateIssuers(),
                tenantId: $data->type === AccountSsoConnectionType::ENTRA ? $this->nullableLower($data->tenantId) : null,
            ));
        } catch (SsoException $exception) {
            $this->logger->info('SSO connection discovery check failed', ['reason' => $exception->getMessage()]);

            throw new AccountSsoConfigurationException(
                $data->type === AccountSsoConnectionType::ENTRA
                    ? __('We couldn\'t find this tenant. Check the directory (tenant) ID.')
                    : __('We couldn\'t find single sign-on settings at this address. Check the issuer URL.'),
                $data->type === AccountSsoConnectionType::ENTRA ? 'tenant_id' : 'issuer',
            );
        }

        return (string) $issuer;
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    private function assertTypeSpecificFields(AccountSsoConnectionData $data, ?string $issuer): void
    {
        if (trim($data->clientId) === '') {
            throw new AccountSsoConfigurationException(__('Enter the client ID.'), 'client_id');
        }

        if ($data->type === AccountSsoConnectionType::ENTRA && preg_match(self::TENANT_ID_PATTERN, (string) $data->tenantId) !== 1) {
            throw new AccountSsoConfigurationException(__('Enter the directory (tenant) ID. It looks like 00000000-0000-0000-0000-000000000000.'), 'tenant_id');
        }

        if ($data->type === AccountSsoConnectionType::GOOGLE_WORKSPACE) {
            $this->assertHostedDomain($data->hostedDomain);
        }

        if (in_array($data->type, [AccountSsoConnectionType::OKTA, AccountSsoConnectionType::OIDC], true)) {
            $this->assertCustomIssuer($issuer);
        }
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    private function assertHostedDomain(?string $hostedDomain): void
    {
        $hostedDomain = $this->nullableLower($hostedDomain);

        if ($hostedDomain === null || preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $hostedDomain) !== 1 || FreeMailDomains::contains($hostedDomain)) {
            throw new AccountSsoConfigurationException(__('Enter your Google Workspace domain, like example.com.'), 'hosted_domain');
        }
    }

    /**
     * @throws AccountSsoConfigurationException
     */
    private function assertCustomIssuer(?string $issuer): void
    {
        $parts = $issuer === null ? false : parse_url($issuer);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? strtolower($parts['host'] ?? '') : '';
        $schemeAllowed = $scheme === 'https' || ($scheme === 'http' && $this->providerRegistry->allowsPrivateIssuers());

        if (! $schemeAllowed || $host === '' || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user'])) {
            throw new AccountSsoConfigurationException(__('Enter the issuer URL, starting with https://.'), 'issuer');
        }

        if (in_array($host, self::RESERVED_ISSUER_HOSTS, true)) {
            throw new AccountSsoConfigurationException(__('Choose the Google Workspace or Microsoft Entra ID option for this provider.'), 'issuer');
        }
    }

    private function identityAttributes(AccountSsoConnectionData $data, string $issuer): array
    {
        return [
            'type' => $data->type->value,
            'issuer' => $issuer,
            'client_id' => trim($data->clientId),
            'tenant_id' => $data->type === AccountSsoConnectionType::ENTRA ? $this->nullableLower($data->tenantId) : null,
            'hosted_domain' => $data->type === AccountSsoConnectionType::GOOGLE_WORKSPACE ? $this->nullableLower($data->hostedDomain) : null,
        ];
    }

    private function policyAttributes(AccountSsoConnectionData $data): array
    {
        return [
            'session_ttl_minutes' => $data->sessionTtlMinutes,
            'jit_provisioning_enabled' => $data->jitProvisioningEnabled,
            'jit_default_role' => $data->jitDefaultRole->value,
        ];
    }

    /**
     * @return int[]
     */
    private function identityUserIds(AccountSsoConnectionDomainObject $connection): array
    {
        return $this->userIdentityRepository
            ->findWhere(['account_sso_connection_id' => $connection->getId()])
            ->map(fn (UserIdentityDomainObject $identity) => $identity->getUserId())
            ->unique()
            ->values()
            ->all();
    }

    private function nullableLower(?string $value): ?string
    {
        $value = $value === null ? null : strtolower(trim($value));

        return $value === '' ? null : $value;
    }
}
