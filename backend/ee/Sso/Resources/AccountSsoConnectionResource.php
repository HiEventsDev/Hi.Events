<?php

namespace HiEvents\Enterprise\Sso\Resources;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin AccountSsoConnectionDomainObject
 */
class AccountSsoConnectionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'provider_key' => $this->getProviderKey(),
            /** @var 'OKTA'|'ENTRA'|'GOOGLE_WORKSPACE'|'OIDC' */
            'type' => $this->getType(),
            'issuer' => $this->getIssuer(),
            'client_id' => $this->getClientId(),
            'tenant_id' => $this->getTenantId(),
            'hosted_domain' => $this->getHostedDomain(),
            /** @var 'ACTIVE'|'INACTIVE' */
            'status' => $this->getStatus(),
            'session_ttl_minutes' => $this->getSessionTtlMinutes(),
            'enforce_sso' => $this->getEnforceSso(),
            'jit_provisioning_enabled' => $this->getJitProvisioningEnabled(),
            'jit_default_role' => $this->getJitDefaultRole(),
            'last_tested_at' => $this->getLastTestedAt(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
