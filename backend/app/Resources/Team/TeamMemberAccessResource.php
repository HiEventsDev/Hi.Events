<?php

namespace HiEvents\Resources\Team;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Auth\Access\DTO\TeamMemberAccessDTO;
use Illuminate\Http\Request;

/**
 * @mixin TeamMemberAccessDTO
 */
class TeamMemberAccessResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            /** @var 'ADMIN'|'ORGANIZER'|'VIEWER'|'SUPERADMIN' */
            'role' => $this->role,
            /** @var 'ACTIVE'|'INACTIVE'|'INVITED' */
            'status' => $this->status,
            /** @var 'ACCOUNT'|'ORGANIZER'|'EVENT' */
            'access_via' => $this->access_via->value,
        ];
    }
}
