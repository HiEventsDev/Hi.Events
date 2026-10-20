<?php

namespace HiEvents\Enterprise\Sso\Http\Request;

use HiEvents\DomainObjects\Enums\AccountSsoConnectionType;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\Sso\Services\Domain\DTO\AccountSsoConnectionData;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpsertAccountSsoConnectionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(AccountSsoConnectionType::valuesArray())],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:2000'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'string', 'max:64'],
            'hosted_domain' => ['nullable', 'string', 'max:253'],
            'session_ttl_minutes' => ['nullable', 'integer', 'min:15', 'max:10080'],
            'jit_provisioning_enabled' => ['nullable', 'boolean'],
            'jit_default_role' => ['nullable', Rule::in(Role::getAssignableRoles())],
        ];
    }

    public function toConnectionData(): AccountSsoConnectionData
    {
        return new AccountSsoConnectionData(
            type: AccountSsoConnectionType::from($this->validated('type')),
            clientId: (string) $this->validated('client_id'),
            clientSecret: $this->validated('client_secret'),
            issuer: $this->validated('issuer'),
            tenantId: $this->validated('tenant_id'),
            hostedDomain: $this->validated('hosted_domain'),
            sessionTtlMinutes: (int) ($this->validated('session_ttl_minutes') ?? 720),
            jitProvisioningEnabled: (bool) $this->validated('jit_provisioning_enabled'),
            jitDefaultRole: Role::from($this->validated('jit_default_role') ?? Role::ORGANIZER->value),
        );
    }
}
