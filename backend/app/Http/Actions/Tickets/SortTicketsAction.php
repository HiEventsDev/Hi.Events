<?php

namespace TicketKitten\Http\Actions\Tickets;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Exceptions\ResourceConflictException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\Request\Ticket\SortTicketsRequest;
use TicketKitten\Service\Handler\Ticket\SortTicketsHandler;

class SortTicketsAction extends BaseAction
{
    public function __construct(
        private readonly SortTicketsHandler $sortTicketsHandler
    )
    {
    }

    public function __invoke(SortTicketsRequest $request, int $eventId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->sortTicketsHandler->handle(
                $eventId,
                $request->validated(),
            );
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->noContentResponse();
    }

}
