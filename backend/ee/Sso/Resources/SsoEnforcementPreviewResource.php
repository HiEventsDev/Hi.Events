<?php

namespace HiEvents\Enterprise\Sso\Resources;

use HiEvents\Enterprise\Sso\Services\Domain\DTO\SsoEnforcementMember;
use HiEvents\Enterprise\Sso\Services\Domain\DTO\SsoEnforcementPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SsoEnforcementPreview
 */
class SsoEnforcementPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'affected_count' => $this->affectedCount,
            'outside_domain_count' => $this->outsideDomainCount,
            'members' => $this->members->map(fn (SsoEnforcementMember $member) => [
                'account_user_id' => $member->accountUserId,
                'user_id' => $member->userId,
                'email' => $member->email,
                'first_name' => $member->firstName,
                'last_name' => $member->lastName,
                'role' => $member->role,
                'is_account_owner' => $member->isAccountOwner,
                'on_verified_domain' => $member->onVerifiedDomain,
                'sso_enforced' => $member->ssoEnforced,
                'sso_available' => $member->ssoAvailable,
            ])->all(),
        ];
    }
}
