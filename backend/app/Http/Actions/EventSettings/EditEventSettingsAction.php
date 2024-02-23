<?php

namespace TicketKitten\Http\Actions\EventSettings;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\UpdateEventSettingsDTO;
use TicketKitten\Http\Request\EventSettings\UpdateEventSettingsRequest;
use TicketKitten\Resources\Event\EventSettingsResource;
use TicketKitten\Service\Handler\EventSettings\UpdateEventSettingsHandler;

class EditEventSettingsAction extends BaseAction
{
    public function __construct(
        private readonly UpdateEventSettingsHandler $updateEventSettingsHandler
    )
    {
    }

    public function __invoke(UpdateEventSettingsRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $settings = array_merge(
            $request->validated(),
            [
                'event_id' => $eventId,
                'account_id' => $this->getAuthenticatedUser()->getAccountId(),
            ],
        );

        $event = $this->updateEventSettingsHandler->handle(
            UpdateEventSettingsDTO::fromArray($settings),
        );

        return $this->resourceResponse(EventSettingsResource::class, $event);
    }
}
