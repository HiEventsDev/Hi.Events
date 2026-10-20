<?php

namespace HiEvents\Services\Infrastructure\Authorization;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\ProfileDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\AccessRestrictedException;
use HiEvents\Exceptions\PermissionRequiredException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;
use HiEvents\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccess;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessService;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Foundation\Application;

readonly class IsAuthorizedService
{
    public function __construct(
        private Application $app,
        private AccountUserRepositoryInterface $accountUserRepository,
        private AccountUserAccessService $accessService,
        private AuthUserService $authUserService,
    ) {}

    public function validateSuperAdmin(UserDomainObject $authUser): void
    {
        if ($authUser->getCurrentAccountUser()?->getRole() !== Role::SUPERADMIN->name) {
            throw new UnauthorizedException(__('You are not authorized to perform this action.'));
        }
    }

    public function validatePermission(Permission $permission, UserDomainObject $authUser): void
    {
        if (! $this->accessFor($authUser)->can($permission)) {
            throw new PermissionRequiredException;
        }
    }

    public function isActionAuthorized(
        int $entityId,
        string $entityType,
        UserDomainObject $authUser,
        int $authAccountId,
        ?Permission $permission = null,
    ): void {
        $this->validateUserStatus($authUser);

        if ($permission !== null) {
            $this->validatePermission($permission, $authUser);
        }

        $access = $this->accessFor($authUser);

        $result = match ($entityType) {
            EventDomainObject::class => $this->validateEvent($entityId, $authAccountId, $access),
            OrganizerDomainObject::class => $this->validateOrganizer($entityId, $authAccountId, $access, readOnly: false),
            ProfileDomainObject::class => $this->validateProfile($entityId, $authAccountId, $access),
            ImageDomainObject::class => $this->validateImage($entityId, $authAccountId, $authUser, $access),
            AccountDomainObject::class => $this->rejectRestricted(
                $access,
                $this->app->make(AccountRepositoryInterface::class)->findById($entityId)?->getId() === $authAccountId,
            ),
            UserDomainObject::class => $this->validateUserUpdate($entityId, $authAccountId, $authUser, $access),
            TaxAndFeesDomainObject::class => $this->rejectRestricted($access, $this->validateTax($entityId, $authAccountId)),
        };

        if (! $result) {
            throw new UnauthorizedException;
        }
    }

    public function isOrganizerReadAuthorized(int $organizerId, UserDomainObject $authUser, int $authAccountId): void
    {
        $this->validateUserStatus($authUser);

        if (! $this->validateOrganizer($organizerId, $authAccountId, $this->accessFor($authUser), readOnly: true)) {
            throw new UnauthorizedException;
        }
    }

    public function isAccountReadAuthorized(int $accountId, UserDomainObject $authUser, int $authAccountId): void
    {
        $this->validateUserStatus($authUser);

        if ($accountId !== $authAccountId) {
            throw new UnauthorizedException;
        }
    }

    private function accessFor(UserDomainObject $authUser): AccountUserAccess
    {
        $accountUser = $authUser->getCurrentAccountUser();

        if ($accountUser === null) {
            throw new UnauthorizedException;
        }

        return $this->accessService->forAccountUser($accountUser);
    }

    private function rejectRestricted(AccountUserAccess $access, bool $belongsToAccount): bool
    {
        if ($belongsToAccount && $access->isRestricted()) {
            throw new AccessRestrictedException;
        }

        return $belongsToAccount;
    }

    private function validateEvent(int $eventId, int $authAccountId, AccountUserAccess $access): bool
    {
        $event = $this->app->make(EventRepositoryInterface::class)->findById($eventId);

        if ($event?->getAccountId() !== $authAccountId) {
            return false;
        }

        if (! $access->canAccessEvent($event->getId(), $event->getOrganizerId())) {
            throw new AccessRestrictedException;
        }

        return true;
    }

    private function validateOrganizer(int $organizerId, int $authAccountId, AccountUserAccess $access, bool $readOnly): bool
    {
        $organizer = $this->app->make(OrganizerRepositoryInterface::class)->findById($organizerId);

        if ($organizer?->getAccountId() !== $authAccountId) {
            return false;
        }

        $allowed = $readOnly
            ? $access->canReadOrganizer($organizer->getId())
            : $access->canAccessOrganizer($organizer->getId());

        if (! $allowed) {
            throw new AccessRestrictedException;
        }

        return true;
    }

    private function validateImage(int $imageId, int $authAccountId, UserDomainObject $authUser, AccountUserAccess $access): bool
    {
        $image = $this->app->make(ImageRepositoryInterface::class)->findById($imageId);

        if ($image?->getAccountId() !== $authAccountId) {
            return false;
        }

        return match ($image->getEntityType()) {
            EventDomainObject::class => $this->validateEvent($image->getEntityId(), $authAccountId, $access),
            OrganizerDomainObject::class => $this->validateOrganizer($image->getEntityId(), $authAccountId, $access, readOnly: false),
            ProfileDomainObject::class => $this->validateProfile($image->getEntityId(), $authAccountId, $access),
            UserDomainObject::class => ! $access->isRestricted() || $image->getEntityId() === $authUser->getId(),
            default => ! $access->isRestricted(),
        };
    }

    private function validateUserUpdate(int $userId, int $authAccountId, UserDomainObject $authUser, AccountUserAccess $access): bool
    {
        $user = $this->app->make(UserRepositoryInterface::class)->findById($userId);

        if ($user === null) {
            return false;
        }

        if ($access->isRestricted() && $user->getId() !== $authUser->getId()) {
            return false;
        }

        return $this->accountUserRepository->findFirstWhere([
            'account_id' => $authAccountId,
            'user_id' => $user->getId(),
        ]) !== null;
    }

    private function validateProfile(int $profileId, int $authAccountId, AccountUserAccess $access): bool
    {
        $profile = $this->app->make(ProfileRepositoryInterface::class)->findFirstWhere([
            ProfileDomainObjectAbstract::ID => $profileId,
            ProfileDomainObjectAbstract::ACCOUNT_ID => $authAccountId,
        ]);

        if ($profile === null) {
            return false;
        }

        return $this->validateOrganizer($profile->getOrganizerId(), $authAccountId, $access, readOnly: false);
    }

    private function validateTax(int $taxOrFeeId, int $authAccountId): bool
    {
        $taxOrFee = $this->app->make(TaxAndFeeRepositoryInterface::class)->findById($taxOrFeeId);

        return $taxOrFee?->getAccountId() === $authAccountId;
    }

    private function validateUserStatus(UserDomainObject $authUser): void
    {
        if ($authUser->getCurrentAccountUser()?->getStatus() !== UserStatus::ACTIVE->name) {
            $this->authUserService->logout();
            throw new UnauthorizedException(__('Your account is not active.'));
        }
    }
}
