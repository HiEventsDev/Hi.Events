<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Enterprise\BoxOffice\Resources\BoxOfficeResource;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO\GetBoxOfficesDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\GetBoxOfficesHandler;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetBoxOfficesAction extends BaseAction
{
    public function __construct(
        private readonly GetBoxOfficesHandler $getBoxOfficesHandler,
    ) {}

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->filterableResourceResponse(
            resource: BoxOfficeResource::class,
            data: $this->getBoxOfficesHandler->handle(
                new GetBoxOfficesDTO(
                    event_id: $eventId,
                    query_params: $this->getPaginationQueryParams($request),
                ),
            ),
            domainObject: BoxOfficeDomainObject::class,
        );
    }
}
