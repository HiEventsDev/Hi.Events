<?php

namespace HiEvents\Services\Domain\Auth\Sso;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoProviderType;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Enterprise\Sso\Services\Domain\AccountSsoProviderFactory;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\DTO\SsoProviderConfig;
use Illuminate\Contracts\Config\Repository as Config;

class SsoProviderRegistry
{
    public function __construct(
        private readonly Config $config,
        private readonly AccountSsoConnectionRepositoryInterface $connectionRepository,
        private readonly AccountSsoProviderFactory $connectionProviderFactory,
    ) {}

    /**
     * @return SsoProviderConfig[]
     */
    public function all(): array
    {
        $providers = [];

        foreach ((array) $this->config->get('sso.providers', []) as $key => $settings) {
            $provider = $this->build((string) $key, (array) $settings);

            if ($provider !== null) {
                $providers[$provider->key] = $provider;
            }
        }

        return $providers;
    }

    /**
     * @throws SsoException
     */
    public function get(string $key, bool $includeInactiveConnection = false): SsoProviderConfig
    {
        if (str_starts_with($key, AccountSsoConnectionDomainObject::PROVIDER_KEY_PREFIX)) {
            return $this->getConnection($key, $includeInactiveConnection);
        }

        return $this->all()[$key] ?? throw $this->notConfigured();
    }

    public function forConnection(AccountSsoConnectionDomainObject $connection): SsoProviderConfig
    {
        return $this->connectionProviderFactory->make($connection, ! $this->allowsPrivateIssuers());
    }

    public function allowsPrivateIssuers(): bool
    {
        return (bool) $this->config->get('sso.allow_private_issuers');
    }

    public function isPasswordLoginDisabled(): bool
    {
        return (bool) $this->config->get('sso.password_login_disabled');
    }

    private function build(string $key, array $settings): ?SsoProviderConfig
    {
        $clientId = $settings['client_id'] ?? null;
        $clientSecret = $settings['client_secret'] ?? null;
        $issuer = $settings['issuer'] ?? null;
        $type = SsoProviderType::tryFrom((string) ($settings['type'] ?? ''));

        if ($type === null || ! $this->filled($clientId) || ! $this->filled($clientSecret) || ! $this->filled($issuer)) {
            return null;
        }

        return new SsoProviderConfig(
            key: $key,
            type: $type,
            name: (string) ($settings['name'] ?? $key),
            issuer: rtrim((string) $issuer, '/'),
            clientId: (string) $clientId,
            clientSecret: (string) $clientSecret,
            logoUrl: $this->filled($settings['logo_url'] ?? null) ? (string) $settings['logo_url'] : null,
            trustEmail: (bool) ($settings['trust_email'] ?? false),
            restrictNetwork: ! $this->allowsPrivateIssuers(),
        );
    }

    /**
     * @throws SsoException
     */
    private function getConnection(string $key, bool $includeInactive): SsoProviderConfig
    {
        $id = substr($key, strlen(AccountSsoConnectionDomainObject::PROVIDER_KEY_PREFIX));

        /** @var AccountSsoConnectionDomainObject|null $connection */
        $connection = ctype_digit($id) ? $this->connectionRepository->findFirstWhere(['id' => (int) $id]) : null;

        if ($connection === null || (! $includeInactive && ! $connection->isActive())) {
            throw $this->notConfigured();
        }

        return $this->forConnection($connection);
    }

    private function notConfigured(): SsoException
    {
        return new SsoException(SsoErrorCode::PROVIDER_NOT_CONFIGURED, __('This sign-in option is not available.'));
    }

    private function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
