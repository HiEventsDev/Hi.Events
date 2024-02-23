<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Tickets;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Exceptions\InvalidTaxOrFeeIdException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\UpsertTicketDTO;
use TicketKitten\Http\Request\Ticket\UpsertTicketRequest;
use TicketKitten\Http\ResponseCodes;
use TicketKitten\Resources\Ticket\TicketResource;
use TicketKitten\Service\Handler\Ticket\CreateTicketHandler;

class CreateTicketAction extends BaseAction
{
    private CreateTicketHandler $createTicketHandler;

    public function __construct(CreateTicketHandler $handler)
    {
        $this->createTicketHandler = $handler;
    }

    /**
     * @throws Throwable
     */
    public function __invoke(int $eventId, UpsertTicketRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $request->merge([
            'event_id' => $eventId,
            'account_id' => $this->getAuthenticatedUser()->getAccountId(),
        ]);

        try {
            $ticket = $this->createTicketHandler->handle(UpsertTicketDTO::fromArray($request->all()));
        } catch (InvalidTaxOrFeeIdException $e) {
            throw ValidationException::withMessages([
                'tax_and_fee_ids' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(
            resource: TicketResource::class,
            data: $ticket,
            statusCode: ResponseCodes::HTTP_CREATED,
        );
    }
}
