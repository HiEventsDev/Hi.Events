<?php

namespace HiEvents\Enterprise\Sso\Services\Domain;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\Enums\AccountSsoConnectionType;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;

class AccountSsoProviderFactory
{
    public const GOOGLE_ISSUER = 'https://accounts.google.com';

    public const ENTRA_ISSUER_TEMPLATE = 'https://login.microsoftonline.com/%s/v2.0';

    public function make(AccountSsoConnectionDomainObject $connection, bool $restrictNetwork): SsoProviderConfig
    {
        $type = $connection->getConnectionType();

        return new SsoProviderConfig(
            key: $connection->getProviderKey(),
            type: $type->providerType(),
            name: $type->label(),
            issuer: rtrim($connection->getIssuer(), '/'),
            clientId: $connection->getClientId(),
            clientSecret: $connection->getClientSecret(),
            logoUrl: null,
            trustEmail: false,
            restrictNetwork: $restrictNetwork,
            connectionId: $connection->getId(),
            accountId: $connection->getAccountId(),
            tenantId: $type === AccountSsoConnectionType::ENTRA ? $connection->getTenantId() : null,
            hostedDomain: $type === AccountSsoConnectionType::GOOGLE_WORKSPACE ? $connection->getHostedDomain() : null,
        );
    }

    public static function issuerFor(AccountSsoConnectionType $type, ?string $issuer, ?string $tenantId): ?string
    {
        return match ($type) {
            AccountSsoConnectionType::GOOGLE_WORKSPACE => self::GOOGLE_ISSUER,
            AccountSsoConnectionType::ENTRA => $tenantId === null ? null : sprintf(self::ENTRA_ISSUER_TEMPLATE, strtolower($tenantId)),
            AccountSsoConnectionType::OKTA, AccountSsoConnectionType::OIDC => $issuer === null ? null : rtrim(trim($issuer), '/'),
        };
    }
}
