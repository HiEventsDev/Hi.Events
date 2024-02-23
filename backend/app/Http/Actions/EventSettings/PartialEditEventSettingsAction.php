<?php

namespace TicketKitten\Http\Actions\EventSettings;

use Illuminate\Http\JsonResponse;
use Throwable;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\PartialUpdateEventSettingsDTO;
use TicketKitten\Http\Request\EventSettings\UpdateEventSettingsRequest;
use TicketKitten\Resources\Event\EventSettingsResource;
use TicketKitten\Service\Handler\EventSettings\PartialUpdateEventSettingsHandler;

class PartialEditEventSettingsAction extends BaseAction
{
    public function __construct(
        private readonly PartialUpdateEventSettingsHandler $partialUpdateEventSettingsHandler
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(UpdateEventSettingsRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $event = $this->partialUpdateEventSettingsHandler->handle(
            PartialUpdateEventSettingsDTO::fromArray([
                'settings' => $request->validated(),
                'event_id' => $eventId,
                'account_id' => $this->getAuthenticatedUser()->getAccountId(),
            ]),
        );

        return $this->resourceResponse(EventSettingsResource::class, $event);
    }
}
