<?php

namespace HiEvents\Http\Actions\Waitlist\Organizer;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Waitlist\WaitlistEntryResource;
use HiEvents\Services\Application\Handlers\Waitlist\GetWaitlistEntriesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetWaitlistEntriesAction extends BaseAction
{
    public function __construct(
        private readonly GetWaitlistEntriesHandler $handler,
    )
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $entries = $this->handler->handle(
            $eventId,
            $this->getPaginationQueryParams($request),
        );

        return $this->filterableResourceResponse(
            resource: WaitlistEntryResource::class,
            data: $entries,
            domainObject: WaitlistEntryDomainObject::class,
        );
    }
}
