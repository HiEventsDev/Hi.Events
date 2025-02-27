<?php

namespace HiEvents\Http\Actions\Organizers;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Services\Application\Handlers\Organizer\DTO\GetOrganizerEventsDTO;
use HiEvents\Services\Application\Handlers\Organizer\GetOrganizerEventsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOrganizerEventsAction extends BaseAction
{
    public function __construct(
        private readonly GetOrganizerEventsHandler $getOrganizerEventsHandler,
    )
    {
    }

    public function __invoke(int $organizerId, Request $request): JsonResponse
    {
        $this->isActionAuthorized(
            entityId: $organizerId,
            entityType: OrganizerDomainObject::class
        );

        $events = $this->getOrganizerEventsHandler->handle(new GetOrganizerEventsDTO(
            organizerId: $organizerId,
            accountId: $this->getAuthenticatedAccountId(),
            queryParams: QueryParamsDTO::fromArray($request->query->all())
        ));

        return $this->filterableResourceResponse(
            resource: EventResource::class,
            data: $events,
            domainObject: EventDomainObject::class
        );
    }
}
