<?php

namespace TicketKitten\Service\Authorization;

use Illuminate\Foundation\Application;
use TicketKitten\DomainObjects\AccountDomainObject;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\OrganizerDomainObject;
use TicketKitten\DomainObjects\TaxAndFeesDomainObject;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Exceptions\UnauthorizedException;
use TicketKitten\Repository\Interfaces\AccountRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrganizerRepositoryInterface;
use TicketKitten\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;

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
