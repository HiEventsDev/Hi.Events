<?php

namespace HiEvents\Http\Actions\Tickets;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Ticket\SortTicketsRequest;
use HiEvents\Services\Handlers\Ticket\SortTicketsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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
