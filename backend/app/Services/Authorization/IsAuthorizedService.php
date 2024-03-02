<?php

namespace HiEvents\Services\Authorization;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Foundation\Application;

class IsAuthorizedService
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @todo This is a very simplistic way of handling roles. Currently we have an ADMIN and ORGANIZER role, but we
     *      will have a more granular approach to roles.
     */
    public function validateUserRole(Role $minimumRole, UserDomainObject $authUser): void
    {
        if ($minimumRole === Role::ADMIN && $authUser->getRole() !== Role::ADMIN->name) {
            throw new UnauthorizedException(__('You are not authorized to perform this action.'));
        }
    }

    public function isActionAuthorized(int $entityId, string $entityType, UserDomainObject $authUser, Role $minimumRole): void
    {
        $this->validateUserRole($minimumRole, $authUser);

        $repository = match ($entityType) {
            EventDomainObject::class => $this->app->make(EventRepositoryInterface::class),
            AccountDomainObject::class => $this->app->make(AccountRepositoryInterface::class),
            UserDomainObject::class => $this->app->make(UserRepositoryInterface::class),
            TaxAndFeesDomainObject::class => $this->app->make(TaxAndFeeRepositoryInterface::class),
            OrganizerDomainObject::class => $this->app->make(OrganizerRepositoryInterface::class),
        };

        $entity = $repository->findById($entityId);

        $result = match ($entityType) {
            EventDomainObject::class,
            OrganizerDomainObject::class => $entity?->getAccountId() === $authUser->getAccountId(),
            AccountDomainObject::class => $entity?->getId() === $authUser->getAccountId(),
            UserDomainObject::class => $this->validateUserUpdate($entity, $authUser),
            TaxAndFeesDomainObject::class => $this->validateTax($entity, $authUser),
        };

        if (!$result) {
            throw new UnauthorizedException();
        }
    }

    private function validateUserUpdate(?UserDomainObject $entity, UserDomainObject $authUser): bool
    {
        if ($entity === null) {
            return false;
        }

        if ($entity->getAccountId() === $authUser->getAccountId()) {
            return true;
        }

        return false;
    }

    private function validateTax(?TaxAndFeesDomainObject $entity, UserDomainObject $authUser): bool
    {
        if ($entity === null) {
            return false;
        }

        if ($entity->getAccountId() === $authUser->getAccountId()) {
            return true;
        }

        return false;
    }
}
