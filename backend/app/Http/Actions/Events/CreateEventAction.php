<?php

namespace HiEvents\Http\Actions\Events;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;
use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\CreateEventDTO;
use HiEvents\Http\Request\Event\CreateEventRequest;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Service\Handler\Event\CreateEventHandler;

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
