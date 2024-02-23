<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Tickets;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Exceptions\CannotChangeTicketTypeException;
use TicketKitten\Exceptions\InvalidTaxOrFeeIdException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\UpsertTicketDTO;
use TicketKitten\Http\Request\Ticket\UpsertTicketRequest;
use TicketKitten\Resources\Ticket\TicketResource;
use TicketKitten\Service\Handler\Ticket\EditTicketHandler;

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
            'account_id' => $this->getAuthenticatedUser()->getAccountId(),
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
