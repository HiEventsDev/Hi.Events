<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Tickets;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\CannotChangeTicketTypeException;
use HiEvents\Exceptions\InvalidTaxOrFeeIdException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Ticket\UpsertTicketRequest;
use HiEvents\Resources\Ticket\TicketResource;
use HiEvents\Services\Handlers\Ticket\DTO\UpsertTicketDTO;
use HiEvents\Services\Handlers\Ticket\EditTicketHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditTicketAction extends BaseAction
{
    public function __construct(
        private readonly EditTicketHandler $editTicketHandler,
    )
    {
    }

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function __invoke(UpsertTicketRequest $request, int $eventId, int $ticketId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $request->merge([
            'event_id' => $eventId,
            'account_id' => $this->getAuthenticatedAccountId(),
            'ticket_id' => $ticketId,
        ]);

        try {
            $ticket = $this->editTicketHandler->handle(UpsertTicketDTO::fromArray($request->all()));
        } catch (InvalidTaxOrFeeIdException $e) {
            throw ValidationException::withMessages([
                'tax_and_fee_ids' => $e->getMessage(),
            ]);
        } catch (CannotChangeTicketTypeException $e) {
            throw ValidationException::withMessages([
                'type' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(TicketResource::class, $ticket);
    }
}
