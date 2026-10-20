<?php

namespace HiEvents\Resources\User;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessService;
use HiEvents\Services\Domain\Auth\AuthUserService;
use HiEvents\Services\Domain\Auth\PasswordLoginPolicy;
use Illuminate\Http\Request;

/**
 * @mixin UserDomainObject
 */
class UserResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $authUserService = app(AuthUserService::class);
        $isImpersonating = $authUserService->isImpersonating();
        $impersonatorId = $authUserService->getImpersonatorId();

        return [
            'id' => $this->getId(),
            'timezone' => $this->getTimezone(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'full_name' => $this->getFullName(),
            'email' => $this->getEmail(),
            'is_email_verified' => $this->getEmailVerifiedAt() !== null,
            'locale' => $this->getLocale(),
            'two_factor_enabled' => $this->getTwoFactorConfirmedAt() !== null,
            $this->mergeWhen($this->isCurrentUser(), fn () => [
                'has_pending_email_change' => $this->getPendingEmail() !== null,
                /** @var bool */
                'has_password' => app(PasswordLoginPolicy::class)->canConfirmWithPassword($this->resource),
                'marketing_opted_in_at' => $this->getMarketingOptedInAt(),
            ]),
            $this->mergeWhen($isImpersonating, [
                'is_impersonating' => true,
                'impersonator_id' => $impersonatorId,
            ]),
            $this->mergeWhen(config('app.enforce_email_confirmation_during_registration'), fn () => [
                'enforce_email_confirmation_during_registration' => true,
            ]),
            $this->mergeWhen($this->getCurrentAccountUser() !== null, fn () => [
                'role' => $this->getCurrentAccountUser()?->getRole(),
                'is_account_owner' => $this->getCurrentAccountUser()?->getIsAccountOwner(),
                'last_login_at' => $this->getCurrentAccountUser()?->getLastLoginAt(),
                'status' => $this->getCurrentAccountUser()?->getStatus(),
                'account_id' => $this->getCurrentAccountUser()?->getAccountId(),
                'notify_new_orders' => $this->getCurrentAccountUser()?->getNotifyNewOrders(),
                /** @var 'SSO'|'EXEMPT'|'PASSWORD'|null */
                'sso_sign_in' => $this->getCurrentAccountUser()?->getSsoSignInRequirement()?->value,
                'access' => $this->accessSummary(),
            ]),
            $this->mergeWhen($this->isCurrentUser() && $this->getPendingEmail() !== null, fn () => [
                'pending_email' => $this->getPendingEmail(),
            ]),
        ];
    }

    private function isCurrentUser(): bool
    {
        return auth()->id() !== null && (int) auth()->id() === $this->getId();
    }

    private function accessSummary(): array
    {
        $access = app(AccountUserAccessService::class)->forAccountUser($this->getCurrentAccountUser());

        return [
            /** @var 'ACCOUNT'|'RESTRICTED' */
            'scope' => $access->scope->value,
            /** @var int[] */
            'organizer_ids' => $access->organizerIds,
            /** @var int[] */
            'event_ids' => $access->eventIds,
            /** @var array<int, 'EDIT'|'VIEW_FINANCIALS'|'ISSUE_REFUNDS'|'MANAGE_PAYMENTS'|'DELETE_EVENTS'|'MANAGE_TEAM'|'MANAGE_ACCOUNT'> */
            'permissions' => array_map(static fn (Permission $permission) => $permission->value, $access->permissions),
        ];
    }
}
