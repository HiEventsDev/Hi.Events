<?php

namespace TicketKitten\Http\Actions\Events;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Exceptions\AccountNotVerifiedException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\UpdateEventStatusDTO;
use TicketKitten\Http\Request\Event\UpdateEventStatusRequest;
use TicketKitten\Http\ResponseCodes;
use TicketKitten\Resources\Event\EventResource;
use TicketKitten\Service\Handler\Event\UpdateEventStatusHandler;

class UpdateEventStatusAction extends BaseAction
{
    public function __construct(
        private readonly UpdateEventStatusHandler $updateEventStatusHandler,
    )
    {
    }

    public function __invoke(UpdateEventStatusRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $updatedEvent = $this->updateEventStatusHandler->handle(UpdateEventStatusDTO::fromArray([
                'status' => $request->input('status'),
                'eventId' => $eventId,
                'accountId' => $this->getAuthenticatedUser()->getAccountId(),
            ]));
        } catch (AccountNotVerifiedException $e) {
            return $this->errorResponse($e->getMessage(), ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->resourceResponse(EventResource::class, $updatedEvent);
    }
}
