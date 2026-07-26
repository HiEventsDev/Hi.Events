<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Events;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Support\Facades\Log;

abstract class BasePublicEventAction extends BaseAction
{
    protected function canUserViewEvent(EventDomainObject $event): bool
    {
        if ($event->getStatus() === EventStatus::LIVE->name) {
            return true;
        }

        if ($this->isUserAuthenticated() && $event->getAccountId() === $this->getAuthenticatedAccountId()) {
            return true;
        }

        if ($this->isUserAuthenticated() && $this->getAuthenticatedUserRole() === Role::SUPERADMIN) {
            Log::debug(__('Superadmin user is viewing non-live event with ID :eventId', [
                'eventId' => $event->getId(),
                'accountId' => $this->getAuthenticatedAccountId(),
            ]));

            return true;
        }

        return false;
    }
}
