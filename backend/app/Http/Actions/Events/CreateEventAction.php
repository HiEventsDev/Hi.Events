<?php

namespace TicketKitten\Http\Actions\Events;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;
use TicketKitten\Exceptions\OrganizerNotFoundException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\CreateEventDTO;
use TicketKitten\Http\Request\Event\CreateEventRequest;
use TicketKitten\Resources\Event\EventResource;
use TicketKitten\Service\Handler\Event\CreateEventHandler;

class CreateEventAction extends BaseAction
{
    public function __construct(private CreateEventHandler $createEventHandler)
    {
    }

    /**
     * @throws ValidationException|Throwable
     */
    public function __invoke(CreateEventRequest $request): JsonResponse
    {
        $authorisedUser = $this->getAuthenticatedUser();

        $eventData = array_merge(
            $request->validated(),
            [
                'account_id' => $authorisedUser->getAccountId(),
                'user_id' => $authorisedUser->getId(),
            ]
        );

        try {
            $event = $this->createEventHandler->handle(
                eventData: CreateEventDTO::fromArray($eventData)
            );
        } catch (OrganizerNotFoundException $e) {
            throw ValidationException::withMessages([
                'organizer_id' => $e->getMessage(),
            ]);
        }

        return $this->resourceResponse(EventResource::class, $event);
    }
}
