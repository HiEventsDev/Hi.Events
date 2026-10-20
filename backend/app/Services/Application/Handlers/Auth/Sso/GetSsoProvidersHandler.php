<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso;

use HiEvents\DomainObjects\Status\AccountSsoConnectionStatus;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use HiEvents\Services\Domain\Auth\Sso\SsoProviderRegistry;
use Illuminate\Contracts\Config\Repository as Config;

class GetSsoProvidersHandler
{
    public function __construct(
        private readonly SsoProviderRegistry $providerRegistry,
        private readonly Config $config,
        private readonly AccountSsoConnectionRepositoryInterface $connectionRepository,
    ) {}

    /**
     * @return array{providers: array<int, array{key: string, name: string, type: string, logo_url: ?string}>, password_login_enabled: bool, registration_enabled: bool, buttons_visible: bool, enterprise_sso_enabled: bool}
     */
    public function handle(): array
    {
        return [
            'providers' => array_values(array_map(
                static fn (SsoProviderConfig $provider) => [
                    'key' => $provider->key,
                    'name' => $provider->name,
                    'type' => $provider->type->value,
                    'logo_url' => $provider->logoUrl,
                ],
                $this->providerRegistry->all(),
            )),
            'password_login_enabled' => ! $this->providerRegistry->isPasswordLoginDisabled(),
            'registration_enabled' => ! $this->config->get('app.disable_registration'),
            'buttons_visible' => $this->providerRegistry->areButtonsVisible(),
            'enterprise_sso_enabled' => $this->connectionRepository->countWhere(['status' => AccountSsoConnectionStatus::ACTIVE->value]) > 0,
        ];
    }
}
