<?php

namespace TicketKitten\Resources\User;

use Illuminate\Http\Request;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Resources\BaseResource;

/**
 * @mixin UserDomainObject
 */
class UserResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'account_id' => $this->getAccountId(),
            'timezone' => $this->getTimezone(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'email' => $this->getEmail(),
            'status' => $this->getStatus(),
            'has_pending_email_change' => $this->getPendingEmail() !== null,
            'role' => $this->getRole(),
            'last_login_at' => $this->getLastLoginAt(),
            'is_account_owner' => $this->getIsAccountOwner(),
            $this->mergeWhen($this->getPendingEmail() !== null, [
                'pending_email' => $this->getPendingEmail(),
            ]),
        ];
    }
}
