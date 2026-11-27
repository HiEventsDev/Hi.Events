<?php

namespace HiEvents\Http\Actions\TicketResale;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Resources\TicketResale\TicketResaleListingResource;
use HiEvents\Services\Application\Handlers\TicketResale\GetTicketResaleListingsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetTicketResaleListingsAction extends BaseAction
{
    public function __construct(
        private readonly GetTicketResaleListingsHandler $handler,
    ) {}

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->filterableResourceResponse(
            resource: TicketResaleListingResource::class,
            data: $this->handler->handle($eventId, QueryParamsDTO::fromArray($request->query->all())),
            domainObject: TicketResaleListingDomainObject::class,
        );
    }
}
