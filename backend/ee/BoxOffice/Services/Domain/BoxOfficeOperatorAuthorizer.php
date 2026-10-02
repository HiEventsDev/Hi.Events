<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Services\Infrastructure\Authorization\IsAuthorizedService;

class BoxOfficeOperatorAuthorizer
{
    public function __construct(
        private readonly IsAuthorizedService $isAuthorizedService,
    ) {}

    public function canOperateWithoutPin(
        EventDomainObject $event,
        ?UserDomainObject $user,
        ?int $accountId,
    ): bool {
        if ($user === null || $accountId === null) {
            return false;
        }

        try {
            $this->isAuthorizedService->isActionAuthorized(
                $event->getId(),
                EventDomainObject::class,
                $user,
                $accountId,
                Role::ORGANIZER,
            );
        } catch (UnauthorizedException) {
            return false;
        }

        return true;
    }
}
