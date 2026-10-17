<?php

namespace HiEvents\Resources\Auth\Sso;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\DomainObjects\UserIdentityDomainObject;
use HiEvents\Exceptions\SsoException;
use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Auth\Sso\SsoProviderRegistry;
use Illuminate\Http\Request;

/**
 * @mixin UserIdentityDomainObject
 */
class UserIdentityResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'provider_key' => $this->getProviderKey(),
            'email' => $this->getEmail(),
            'last_used_at' => $this->getLastUsedAt(),
            'created_at' => $this->getCreatedAt(),
            /** @var array{key: string, name: string, type: string, logo_url: null}|null */
            'organization_provider' => $this->organizationProvider(),
        ];
    }

    private function organizationProvider(): ?array
    {
        if (! str_starts_with($this->getProviderKey(), AccountSsoConnectionDomainObject::PROVIDER_KEY_PREFIX)) {
            return null;
        }

        try {
            $provider = app(SsoProviderRegistry::class)->get($this->getProviderKey());
        } catch (SsoException) {
            return null;
        }

        return [
            'key' => $provider->key,
            'name' => $provider->name,
            'type' => $provider->type->value,
            'logo_url' => null,
        ];
    }
}
