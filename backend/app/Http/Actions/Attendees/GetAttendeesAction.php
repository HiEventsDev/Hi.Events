<?php

namespace HiEvents\Http\Actions\Attendees;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Resources\Attendee\AttendeeResource;
use HiEvents\Services\Application\Handlers\Attendee\GetAttendeesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAttendeesAction extends BaseAction
{
    public function __construct(
        private readonly GetAttendeesHandler $getAttendeesHandler,
    )
    {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $attendees = $this->getAttendeesHandler->handle(
            eventId: $eventId,
            queryParams: QueryParamsDTO::fromArray($request->query->all())
        );

        return $this->filterableResourceResponse(
            resource: AttendeeResource::class,
            data: $attendees,
            domainObject: AttendeeDomainObject::class,
        );
    }
}
