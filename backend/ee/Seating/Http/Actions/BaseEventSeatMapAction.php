<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Exceptions\FeatureNotEnabledException;

abstract class BaseEventSeatMapAction extends BaseSeatingAction
{
    /**
     * @throws FeatureNotEnabledException
     */
    protected function authorizeEventSeatMapAccess(int $eventId): void
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        if (! app(EventSeatMapLookupService::class)->existsForEvent($eventId)) {
            $this->assertSeatingEnabled();
        }
    }
}
