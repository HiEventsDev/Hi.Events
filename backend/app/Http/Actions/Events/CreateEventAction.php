<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Event\CreateEventRequest;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Services\Application\Handlers\Event\CreateEventHandler;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateEventAction extends BaseAction
{
    public function __construct(
        private readonly CreateEventHandler $createEventHandler
    ) {}

    /**
     * @throws ValidationException|Throwable
     */
    public function __invoke(CreateEventRequest $request): JsonResponse
    {
        $authorisedUser = $this->getAuthenticatedUser();

        $validated = $request->validated();
        $eventLocationPayload = $validated['event_location'] ?? null;
        unset($validated['event_location']);

        $eventData = array_merge(
            $validated,
            [
                'account_id' => $this->getAuthenticatedAccountId(),
                'user_id' => $authorisedUser->getId(),
                'event_location' => $eventLocationPayload !== null
                    ? EventLocationData::fromArray($eventLocationPayload)
                    : null,
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
