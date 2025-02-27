<?php

namespace HiEvents\Http\Actions\CheckInLists;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\CheckInList\CheckInListResource;
use HiEvents\Services\Application\Handlers\CheckInList\DTO\GetCheckInListsDTO;
use HiEvents\Services\Application\Handlers\CheckInList\GetCheckInListsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCheckInListsAction extends BaseAction
{
    public function __construct(
        private readonly GetCheckInListsHandler $getCheckInListsHandler,
    )
    {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->filterableResourceResponse(
            resource: CheckInListResource::class,
            data: $this->getCheckInListsHandler->handle(
                GetCheckInListsDTO::fromArray([
                    'eventId' => $eventId,
                    'queryParams' => $this->getPaginationQueryParams($request),
                ]),
            ),
            domainObject: CheckInListDomainObject::class,
        );
    }
}
