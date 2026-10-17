<?php

namespace HiEvents\Resources\Auth\Sso;

use HiEvents\DomainObjects\UserIdentityDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Auth\Sso\UserIdentityService;
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
        $provider = app(UserIdentityService::class)->usableConnectionProvider($this->resource);

        if ($provider === null) {
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
