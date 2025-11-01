<?php

namespace HiEvents\Resources\User;

use Exception;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin UserDomainObject
 */
class UserResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $isImpersonating = false;
        $impersonatorId = null;
        try {
            $isImpersonating = (bool) auth()->payload()->get('is_impersonating', false);
            $impersonatorId = $isImpersonating ? auth()->payload()->get('impersonator_id') : null;
        } catch (Exception) {
            // Not authenticated or no JWT token
        }

        return [
            'id' => $this->getId(),
            'timezone' => $this->getTimezone(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'full_name' => $this->getFullName(),
            'email' => $this->getEmail(),
            'is_email_verified' => $this->getEmailVerifiedAt() !== null,
            'has_pending_email_change' => $this->getPendingEmail() !== null,
            'locale' => $this->getLocale(),
            $this->mergeWhen($isImpersonating, [
                'is_impersonating' => true,
                'impersonator_id' => $impersonatorId,
            ]),
            $this->mergeWhen(config('app.enforce_email_confirmation_during_registration'), fn() => [
                'enforce_email_confirmation_during_registration' => true,
            ]),
            $this->mergeWhen($this->getCurrentAccountUser() !== null, fn() => [
                'role' => $this->getCurrentAccountUser()?->getRole(),
                'is_account_owner' => $this->getCurrentAccountUser()?->getIsAccountOwner(),
                'last_login_at' => $this->getCurrentAccountUser()?->getLastLoginAt(),
                'status' => $this->getCurrentAccountUser()?->getStatus(),
                'account_id' => $this->getCurrentAccountUser()?->getAccountId(),
            ]),
            $this->mergeWhen($this->getPendingEmail() !== null, [
                'pending_email' => $this->getPendingEmail(),
            ]),
        ];
    }
}
