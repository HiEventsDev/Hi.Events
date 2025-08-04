<?php

namespace HiEvents\Resources\User;

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
