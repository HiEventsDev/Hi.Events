<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Http\Actions;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\Seating\Http\Request\UpsertSeatMapRequest;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\UpsertSeatMapDTO;
use HiEvents\Exceptions\FeatureNotEnabledException;

abstract class BaseSeatMapAction extends BaseSeatingAction
{
    protected function authorizeSeatMapAccess(int $organizerId): void
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);
    }

    /**
     * @throws FeatureNotEnabledException
     */
    protected function authorizeSeatMapSetup(int $organizerId): void
    {
        $this->authorizeSeatMapAccess($organizerId);
        $this->assertSeatingEnabled();
    }

    protected function upsertDtoFromRequest(int $organizerId, UpsertSeatMapRequest $request): UpsertSeatMapDTO
    {
        return new UpsertSeatMapDTO(
            organizer_id: $organizerId,
            account_id: $this->getAuthenticatedAccountId(),
            name: $request->validated('name'),
            layout: $request->validated('layout'),
            version: $request->validated('version'),
        );
    }
}
