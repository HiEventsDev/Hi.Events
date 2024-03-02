<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Tickets;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Handlers\Ticket\DeleteTicketHandler;

class DeleteTicketAction extends BaseAction
{
    private DeleteTicketHandler $deleteTicketHandler;

    public function __construct(DeleteTicketHandler $handler)
    {
        $this->deleteTicketHandler = $handler;
    }

    public function __invoke(int $eventId, int $ticketId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->deleteTicketHandler->handle(
                ticketId: $ticketId,
                eventId: $eventId,
            );
        } catch (CannotDeleteEntityException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: HttpResponse::HTTP_CONFLICT,
            );
        }

        return $this->deletedResponse();
    }
}
