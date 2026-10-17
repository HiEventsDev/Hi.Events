<?php

namespace HiEvents\Resources\Auth;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Auth\Invitation\PendingInvitation;
use Illuminate\Http\Request;

/**
 * @mixin PendingInvitation
 */
class InvitationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'first_name' => $this->user->getFirstName(),
            'last_name' => $this->user->getLastName(),
            'email' => $this->user->getEmail(),
            'timezone' => $this->user->getTimezone(),
            'account_name' => $this->accountName,
            'requires_sign_in' => $this->requiresSignIn,
        ];
    }
}
