<?php

namespace HiEvents\Resources\Auth;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Application\Handlers\Auth\DTO\InvitationDetailsDTO;
use Illuminate\Http\Request;

/**
 * @mixin InvitationDetailsDTO
 */
class InvitationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'first_name' => $this->invitation->user->getFirstName(),
            'last_name' => $this->invitation->user->getLastName(),
            'email' => $this->invitation->user->getEmail(),
            'timezone' => $this->invitation->user->getTimezone(),
            'account_name' => $this->invitation->accountName,
            'requires_sign_in' => $this->invitation->requiresSignIn,
            'sso_required' => $this->ssoRequired,
        ];
    }
}
