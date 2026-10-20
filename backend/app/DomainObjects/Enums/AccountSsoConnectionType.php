<?php

namespace HiEvents\DomainObjects\Enums;

enum AccountSsoConnectionType: string
{
    use BaseEnum;

    case OKTA = 'OKTA';
    case ENTRA = 'ENTRA';
    case GOOGLE_WORKSPACE = 'GOOGLE_WORKSPACE';
    case OIDC = 'OIDC';

    public function providerType(): SsoProviderType
    {
        return match ($this) {
            self::ENTRA => SsoProviderType::MICROSOFT,
            self::GOOGLE_WORKSPACE => SsoProviderType::GOOGLE,
            self::OKTA, self::OIDC => SsoProviderType::OIDC,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OKTA => 'Okta',
            self::ENTRA => 'Microsoft Entra ID',
            self::GOOGLE_WORKSPACE => 'Google Workspace',
            self::OIDC => 'SSO',
        };
    }
}
