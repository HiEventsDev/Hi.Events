<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Tickets;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\TicketDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Ticket\TicketResource;
use HiEvents\Services\Handlers\Ticket\GetTicketsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetTicketsAction extends BaseAction
{
    public function __construct(
        private readonly GetTicketsHandler $getTicketsHandler,
    )
    {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $tickets = $this->getTicketsHandler->handle(
            eventId: $eventId,
            queryParamsDTO: $this->getPaginationQueryParams($request),
        );

        return $this->filterableResourceResponse(
            resource: TicketResource::class,
            data: $tickets,
            domainObject: TicketDomainObject::class
        );
    }
}
